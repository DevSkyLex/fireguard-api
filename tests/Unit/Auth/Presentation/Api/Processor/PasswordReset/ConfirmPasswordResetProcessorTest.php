<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor\PasswordReset;

use ApiPlatform\Metadata\Post;
use Auth\Application\UseCase\Command\PasswordReset\ConfirmPasswordReset\{ConfirmPasswordResetCommand, ConfirmPasswordResetResult};
use Auth\Presentation\Api\Dto\Input\PasswordReset\ConfirmPasswordResetInput;
use Auth\Presentation\Api\Dto\Output\PasswordReset\ConfirmPasswordResetOutput;
use Auth\Presentation\Api\Processor\PasswordReset\ConfirmPasswordResetProcessor;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use stdClass;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, TooManyRequestsHttpException, UnauthorizedHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function hash;
use function sprintf;
use function substr;

#[CoversClass(ConfirmPasswordResetProcessor::class)]
final class ConfirmPasswordResetProcessorTest extends TestCase
{
  #[Test]
  public function testProcessReturnsOutputOnSuccess(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/auth/password/reset/confirm',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (ConfirmPasswordResetCommand $command): bool => 'challenge-123' === $command->token
          && '123456' === $command->code
          && 'Secret123!' === $command->newPassword
          && '127.0.0.1' === $command->ipAddress,
      ))
      ->willReturn(ConfirmPasswordResetResult::success());

    $processor = new ConfirmPasswordResetProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
    );

    $input = new ConfirmPasswordResetInput();
    $input->token = 'challenge-123';
    $input->code = '123456';
    $input->newPassword = 'Secret123!';

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(ConfirmPasswordResetOutput::class, $output);
    self::assertTrue($output->success);
    self::assertNull($output->errorCode);
  }

  #[Test]
  public function testProcessThrowsUnauthorizedWhenInvalidCode(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/auth/password/reset/confirm',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(ConfirmPasswordResetResult::failed(
        message: 'Invalid verification code.',
        errorCode: ConfirmPasswordResetResult::ERROR_INVALID_CODE,
        attemptsRemaining: 2,
      ));

    $processor = new ConfirmPasswordResetProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
    );

    $input = new ConfirmPasswordResetInput();
    $input->token = 'challenge-123';
    $input->code = '000000';
    $input->newPassword = 'Secret123!';

    $this->expectException(UnauthorizedHttpException::class);

    $processor->process($input, new Post());
  }

  #[Test]
  public function testProcessThrowsTooManyRequestsWhenRateLimited(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/auth/password/reset/confirm',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create($this->createRateLimitKey('challenge-123', '127.0.0.1'))->consume();

    $processor = new ConfirmPasswordResetProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      requestStack: $requestStack,
      rateLimiter: $rateLimiter,
    );

    $input = new ConfirmPasswordResetInput();
    $input->token = 'challenge-123';
    $input->code = '123456';
    $input->newPassword = 'Secret123!';

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process($input, new Post());
  }

  #[Test]
  public function testProcessRejectsUnexpectedInputType(): void
  {
    $processor = new ConfirmPasswordResetProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      requestStack: new RequestStack(),
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid input data');

    $processor->process(new stdClass(), new Post());
  }

  #[Test]
  public function testProcessThrowsBadRequestForUnknownErrorCode(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/auth/password/reset/confirm',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(ConfirmPasswordResetResult::failed(
        message: 'Password does not meet policy.',
        errorCode: 'weak_password',
      ));

    $processor = new ConfirmPasswordResetProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
    );

    $input = new ConfirmPasswordResetInput();
    $input->token = 'challenge-123';
    $input->code = '123456';
    $input->newPassword = 'weak';

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Password does not meet policy.');

    $processor->process($input, new Post());
  }

  private function createRateLimiterFactory(int $limit = 10): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'password_reset_confirm',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
  }

  private function createRateLimitKey(string $token, string $ipAddress): string
  {
    return sprintf(
      'password_reset_confirm_%s_%s',
      substr(hash('sha256', $token), 0, 16),
      substr(hash('sha256', $ipAddress), 0, 16),
    );
  }
}
