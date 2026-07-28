<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Presentation\Api\Processor\Totp;

use ApiPlatform\Metadata\Post;
use Otp\Application\Exception\TotpEnrollmentNotEnabledException;
use Otp\Application\UseCase\Command\Totp\DisableTotp\{DisableTotpCommand, DisableTotpResult};
use Otp\Presentation\Api\Dto\Input\Totp\DisableTotpInput;
use Otp\Presentation\Api\Dto\Output\Totp\DisableTotpOutput;
use Otp\Presentation\Api\Processor\Totp\DisableTotpProcessor;
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
 * Test DisableTotpProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DisableTotpProcessor::class)]
final class DisableTotpProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new DisableTotpProcessor(
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
        static fn (DisableTotpCommand $command) => 'user-1' === $command->userId && '123456' === $command->code,
      ))
      ->willReturn(DisableTotpResult::success());

    $processor = new DisableTotpProcessor(commandBus: $commandBus, security: $security);

    $output = $processor->process($this->input('123456'), new Post());

    self::assertInstanceOf(DisableTotpOutput::class, $output);
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
      ->willReturn(DisableTotpResult::failed(error: 'Invalid verification code.'));

    $processor = new DisableTotpProcessor(commandBus: $commandBus, security: $security);

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process($this->input('000000'), new Post());
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenNotEnabled(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(TotpEnrollmentNotEnabledException::forUser('user-1'));

    $processor = new DisableTotpProcessor(commandBus: $commandBus, security: $security);

    $this->expectException(NotFoundHttpException::class);

    $processor->process($this->input('123456'), new Post());
  }

  #[Test]
  public function testProcessPrefersTheImmutableUserIdOverTheIdentifier(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->userWithId('550e8400-e29b-41d4-a716-446655440778'));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (DisableTotpCommand $command): bool => '550e8400-e29b-41d4-a716-446655440778' === $command->userId,
      ))
      ->willReturn(DisableTotpResult::success());

    $processor = new DisableTotpProcessor(commandBus: $commandBus, security: $security);

    self::assertTrue($processor->process($this->input('123456'), new Post())->success);
  }

  #[Test]
  public function testProcessUnwrapsTheNotEnabledFromABareHandlerFailure(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->processWithDispatchFailure(new HandlerFailedException(
      new Envelope(new stdClass()),
      [TotpEnrollmentNotEnabledException::forUser('user-1')],
    ));
  }

  #[Test]
  public function testProcessRethrowsAHandlerFailureWithoutANotEnabledCause(): void
  {
    $this->expectException(HandlerFailedException::class);

    $this->processWithDispatchFailure(new HandlerFailedException(
      new Envelope(new stdClass()),
      [new RuntimeException('handler blew up')],
    ));
  }

  #[Test]
  public function testProcessUnwrapsTheNotEnabledFromAWrappedHandlerFailure(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('TOTP is not enabled');

    $this->processWithDispatchFailure(MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new stdClass()),
      [new RuntimeException('unrelated'), TotpEnrollmentNotEnabledException::forUser('user-1')],
    )));
  }

  #[Test]
  public function testProcessUnwrapsTheNotEnabledFromAMessengerPreviousChain(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->processWithDispatchFailure(MessengerRuntimeException::wrap(
      TotpEnrollmentNotEnabledException::forUser('user-1'),
    ));
  }

  #[Test]
  public function testProcessRethrowsAMessengerFailureWithoutANotEnabledCause(): void
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

    $processor = new DisableTotpProcessor(
      commandBus: $commandBus,
      security: $security,
      rateLimiter: $rateLimiter,
    );

    $this->expectException(TooManyRequestsHttpException::class);
    $this->expectExceptionMessage('Too many TOTP disable attempts.');

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
      ->willReturn(DisableTotpResult::success());

    $processor = new DisableTotpProcessor(
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

    $processor = new DisableTotpProcessor(commandBus: $commandBus, security: $security);

    $processor->process($this->input('123456'), new Post());
  }

  private function rateLimiterFactory(int $limit = 10): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'otp_totp_disable',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '15 minutes',
      ],
      storage: new InMemoryStorage(),
    );
  }

  private function rateLimitKey(string $userId): string
  {
    return sprintf('otp_totp_disable_%s', substr(hash('sha256', $userId), 0, 16));
  }

  private function input(string $code): DisableTotpInput
  {
    $input = new DisableTotpInput();
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
