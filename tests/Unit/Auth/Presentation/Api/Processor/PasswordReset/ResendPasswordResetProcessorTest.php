<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor\PasswordReset;

use ApiPlatform\Metadata\Post;
use Auth\Application\UseCase\Command\PasswordReset\ResendPasswordReset\{
  ResendPasswordResetCommand,
  ResendPasswordResetResult
};
use Auth\Presentation\Api\Dto\Input\PasswordReset\ResendPasswordResetInput;
use Auth\Presentation\Api\Processor\PasswordReset\ResendPasswordResetProcessor;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{
  BadRequestHttpException,
  NotFoundHttpException,
  TooManyRequestsHttpException
};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function hash;
use function sprintf;
use function substr;

/**
 * Test ResendPasswordResetProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ResendPasswordResetProcessor::class)]
final class ResendPasswordResetProcessorTest extends TestCase
{
  // #region Constants
  private const string TOKEN = 'reset-token';

  private const string IP = '203.0.113.10';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessRejectsAnInvalidInput(): void
  {
    $this->expectException(InvalidArgumentException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))->process(null, new Post());
  }

  #[Test]
  public function testProcessDispatchesTheCommandAndReturnsTheNewChallenge(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (ResendPasswordResetCommand $command): bool => self::TOKEN === $command->token
        && self::IP === $command->ipAddress))
      ->willReturn($this->successResult());

    $output = $this->createProcessor($commandBus)->process($this->input(), new Post());

    self::assertTrue($output->success);
    self::assertSame('new-challenge-token', $output->challengeToken);
    self::assertSame('u***@example.com', $output->maskedRecipient);
    self::assertSame(60, $output->canResendIn);
  }

  #[Test]
  public function testProcessDefaultsAMissingTokenToAnEmptyString(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (ResendPasswordResetCommand $command): bool => '' === $command->token))
      ->willReturn($this->successResult());

    $this->createProcessor($commandBus)->process(new ResendPasswordResetInput(), new Post());
  }

  #[Test]
  public function testProcessMapsResendNotAllowedToHttp429(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new ResendPasswordResetResult(
      success: false,
      message: 'Please wait 42 seconds before resending.',
      errorCode: ResendPasswordResetResult::ERROR_RESEND_NOT_ALLOWED,
      retryAfter: 42,
    ));

    $this->expectException(TooManyRequestsHttpException::class);

    $this->createProcessor($commandBus)->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessMapsAnInvalidTokenToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new ResendPasswordResetResult(
      success: false,
      message: 'Reset challenge not found.',
      errorCode: ResendPasswordResetResult::ERROR_INVALID_TOKEN,
    ));

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessMapsAnyOtherFailureToHttp400(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new ResendPasswordResetResult(
      success: false,
      message: 'Password reset resend failed.',
      errorCode: 'unexpected',
    ));

    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($commandBus)->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessThrowsTooManyRequestsWhenTheRateLimitIsExhausted(): void
  {
    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create($this->rateLimitKey())->consume();

    $processor = $this->createProcessor($this->createStub(CommandBusPort::class), $rateLimiter);

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessFallsBackToTheLoopbackIpWhenThereIsNoRequest(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (ResendPasswordResetCommand $command): bool => '127.0.0.1' === $command->ipAddress))
      ->willReturn($this->successResult());

    $processor = new ResendPasswordResetProcessor(
      commandBus: $commandBus,
      requestStack: new RequestStack(),
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $processor->process($this->input(), new Post());
  }

  private function input(): ResendPasswordResetInput
  {
    $input = new ResendPasswordResetInput();
    $input->token = self::TOKEN;

    return $input;
  }

  private function createProcessor(
    CommandBusPort $commandBus,
    ?RateLimiterFactory $rateLimiter = null,
  ): ResendPasswordResetProcessor {
    $requestStack = new RequestStack();
    $requestStack->push(new Request(server: ['REMOTE_ADDR' => self::IP]));

    return new ResendPasswordResetProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      rateLimiter: $rateLimiter ?? $this->createRateLimiterFactory(),
    );
  }

  private function createRateLimiterFactory(int $limit = 10): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'password_reset',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
  }

  private function rateLimitKey(): string
  {
    return sprintf(
      'password_reset_%s_%s',
      substr(hash('sha256', self::TOKEN), 0, 16),
      substr(hash('sha256', self::IP), 0, 16),
    );
  }

  private function successResult(): ResendPasswordResetResult
  {
    return new ResendPasswordResetResult(
      success: true,
      message: 'A new password reset code has been sent.',
      errorCode: null,
      retryAfter: 0,
      challengeToken: 'new-challenge-token',
      maskedRecipient: 'u***@example.com',
      expiresAt: new DateTimeImmutable('+10 minutes'),
      maxAttempts: 5,
      canResendIn: 60,
    );
  }
  // #endregion
}
