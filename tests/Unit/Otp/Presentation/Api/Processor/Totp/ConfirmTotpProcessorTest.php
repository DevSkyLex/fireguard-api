<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Presentation\Api\Processor\Totp;

use ApiPlatform\Metadata\Post;
use Otp\Application\Exception\TotpPendingEnrollmentNotFoundException;
use Otp\Application\UseCase\Command\Totp\ConfirmTotp\{ConfirmTotpCommand, ConfirmTotpResult};
use Otp\Presentation\Api\Dto\Input\Totp\ConfirmTotpInput;
use Otp\Presentation\Api\Dto\Output\Totp\ConfirmTotpOutput;
use Otp\Presentation\Api\Processor\Totp\ConfirmTotpProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{NotFoundHttpException, TooManyRequestsHttpException, UnauthorizedHttpException, UnprocessableEntityHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

use function hash;
use function sprintf;
use function substr;

/**
 * Test ConfirmTotpProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConfirmTotpProcessor::class)]
final class ConfirmTotpProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new ConfirmTotpProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
    );

    $this->expectException(UnauthorizedHttpException::class);

    $processor->process($this->input('123456'), new Post());
  }

  #[Test]
  public function testProcessMapsSuccessfulResult(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->user());

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (ConfirmTotpCommand $command) => 'user-1' === $command->userId && '123456' === $command->code,
      ))
      ->willReturn(ConfirmTotpResult::success());

    $processor = new ConfirmTotpProcessor(commandBus: $commandBus, security: $security);

    $output = $processor->process($this->input('123456'), new Post());

    self::assertInstanceOf(ConfirmTotpOutput::class, $output);
    self::assertTrue($output->success);
  }

  #[Test]
  public function testProcessThrowsUnprocessableEntityOnInvalidCode(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(ConfirmTotpResult::failed(attemptsRemaining: 4, error: 'Invalid verification code.'));

    $processor = new ConfirmTotpProcessor(commandBus: $commandBus, security: $security);

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process($this->input('000000'), new Post());
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenNoPendingEnrollment(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(TotpPendingEnrollmentNotFoundException::forUser('user-1'));

    $processor = new ConfirmTotpProcessor(commandBus: $commandBus, security: $security);

    $this->expectException(NotFoundHttpException::class);

    $processor->process($this->input('123456'), new Post());
  }

  #[Test]
  public function testProcessPrefersTheImmutableUserIdOverTheIdentifier(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->userWithId('550e8400-e29b-41d4-a716-446655440777'));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (ConfirmTotpCommand $command): bool => '550e8400-e29b-41d4-a716-446655440777' === $command->userId,
      ))
      ->willReturn(ConfirmTotpResult::success());

    $processor = new ConfirmTotpProcessor(commandBus: $commandBus, security: $security);

    self::assertTrue($processor->process($this->input('123456'), new Post())->success);
  }

  #[Test]
  public function testProcessUnwrapsTheNotFoundFromABareHandlerFailure(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->processWithDispatchFailure(new HandlerFailedException(
      new Envelope(new stdClass()),
      [TotpPendingEnrollmentNotFoundException::forUser('user-1')],
    ));
  }

  #[Test]
  public function testProcessRethrowsAHandlerFailureWithoutAPendingEnrollmentCause(): void
  {
    $this->expectException(HandlerFailedException::class);

    $this->processWithDispatchFailure(new HandlerFailedException(
      new Envelope(new stdClass()),
      [new RuntimeException('handler blew up')],
    ));
  }

  #[Test]
  public function testProcessUnwrapsTheNotFoundFromAWrappedHandlerFailure(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('No pending TOTP setup found');

    $this->processWithDispatchFailure(MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new stdClass()),
      [new RuntimeException('unrelated'), TotpPendingEnrollmentNotFoundException::forUser('user-1')],
    )));
  }

  #[Test]
  public function testProcessUnwrapsTheNotFoundFromAMessengerPreviousChain(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->processWithDispatchFailure(MessengerRuntimeException::wrap(
      TotpPendingEnrollmentNotFoundException::forUser('user-1'),
    ));
  }

  #[Test]
  public function testProcessRethrowsAMessengerFailureWithoutAPendingEnrollmentCause(): void
  {
    $this->expectException(MessengerRuntimeException::class);

    $this->processWithDispatchFailure(MessengerRuntimeException::wrap(new RuntimeException('transport down')));
  }

  #[Test]
  public function testProcessRethrowsAnUnrelatedDispatchFailure(): void
  {
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('boom');

    $this->processWithDispatchFailure(new RuntimeException('boom'));
  }

  #[Test]
  public function testProcessThrowsTooManyRequestsWhenTheRateLimitIsExhausted(): void
  {
    $rateLimiter = $this->rateLimiterFactory(limit: 1);
    $rateLimiter->create($this->rateLimitKey('user-1'))->consume();

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new ConfirmTotpProcessor(
      commandBus: $commandBus,
      security: $security,
      rateLimiter: $rateLimiter,
    );

    $this->expectException(TooManyRequestsHttpException::class);
    $this->expectExceptionMessage('Too many TOTP confirmation attempts.');

    $processor->process($this->input('123456'), new Post());
  }

  #[Test]
  public function testProcessPassesThroughAnAcceptedRateLimit(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(ConfirmTotpResult::success());

    $processor = new ConfirmTotpProcessor(
      commandBus: $commandBus,
      security: $security,
      rateLimiter: $this->rateLimiterFactory(),
    );

    self::assertTrue($processor->process($this->input('123456'), new Post())->success);
  }

  private function processWithDispatchFailure(Throwable $failure): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    $processor = new ConfirmTotpProcessor(commandBus: $commandBus, security: $security);

    $processor->process($this->input('123456'), new Post());
  }

  private function rateLimiterFactory(int $limit = 10): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'otp_totp_confirm',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '15 minutes',
      ],
      storage: new InMemoryStorage(),
    );
  }

  private function rateLimitKey(string $userId): string
  {
    return sprintf('otp_totp_confirm_%s', substr(hash('sha256', $userId), 0, 16));
  }

  private function input(string $code): ConfirmTotpInput
  {
    $input = new ConfirmTotpInput();
    $input->code = $code;

    return $input;
  }

  private function user(): UserInterface
  {
    return new class () implements UserInterface {
      public function getUserIdentifier(): string
      {
        return 'user-1';
      }

      public function getRoles(): array
      {
        return [];
      }

      public function eraseCredentials(): void
      {
      }
    };
  }

  private function userWithId(string $id): UserInterface
  {
    return new class ($id) implements UserInterface {
      public function __construct(private readonly string $id)
      {
      }

      public function getId(): string
      {
        return $this->id;
      }

      public function getUserIdentifier(): string
      {
        return 'user@example.com';
      }

      public function getRoles(): array
      {
        return [];
      }

      public function eraseCredentials(): void
      {
      }
    };
  }
}
