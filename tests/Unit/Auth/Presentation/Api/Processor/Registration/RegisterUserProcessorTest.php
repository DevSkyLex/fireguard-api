<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor\Registration;

use ApiPlatform\Metadata\Post;
use Auth\Application\UseCase\Command\Registration\RegisterUser\{RegisterUserCommand, RegisterUserResult};
use Auth\Presentation\Api\Dto\Input\Registration\RegisterInput;
use Auth\Presentation\Api\Dto\Output\Registration\RegisterOutput;
use Auth\Presentation\Api\Processor\Registration\RegisterUserProcessor;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use stdClass;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{ConflictHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function hash;
use function sprintf;
use function substr;

#[CoversClass(RegisterUserProcessor::class)]
final class RegisterUserProcessorTest extends TestCase
{
  #[Test]
  public function testProcessReturnsOutputOnSuccess(): void
  {
    $expiresAt = new DateTimeImmutable('+1 hour');
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/auth/register',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (RegisterUserCommand $command): bool => 'jane@example.com' === $command->email
          && 'Secret123!' === $command->password
          && 'Jane' === $command->firstName
          && 'Doe' === $command->lastName
          && '127.0.0.1' === $command->ipAddress,
      ))
      ->willReturn(RegisterUserResult::success(
        challengeToken: 'challenge-123',
        maskedRecipient: 'j***@example.com',
        expiresAt: $expiresAt,
        maxAttempts: 10,
        canResendIn: 60,
      ));

    $processor = new RegisterUserProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $input = new RegisterInput();
    $input->firstName = 'Jane';
    $input->lastName = 'Doe';
    $input->email = 'jane@example.com';
    $input->password = 'Secret123!';

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(RegisterOutput::class, $output);
    self::assertTrue($output->success);
    self::assertSame('challenge-123', $output->challengeToken);
    self::assertSame('j***@example.com', $output->maskedRecipient);
    self::assertSame($expiresAt, $output->expiresAt);
    self::assertSame(10, $output->maxAttempts);
    self::assertSame(60, $output->canResendIn);
  }

  #[Test]
  public function testProcessThrowsConflictWhenEmailTaken(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/auth/register',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(RegisterUserResult::failed(
        message: 'An account already exists with this email address.',
        errorCode: RegisterUserResult::ERROR_EMAIL_TAKEN,
      ));

    $processor = new RegisterUserProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $input = new RegisterInput();
    $input->firstName = 'Jane';
    $input->lastName = 'Doe';
    $input->email = 'taken@example.com';
    $input->password = 'Secret123!';

    $this->expectException(ConflictHttpException::class);

    $processor->process($input, new Post());
  }

  #[Test]
  public function testProcessThrowsWhenInputIsNotARegisterInput(): void
  {
    $processor = new RegisterUserProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      requestStack: new RequestStack(),
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid input data');

    $processor->process(new stdClass(), new Post());
  }

  #[Test]
  public function testProcessRejectsOnceTheRateLimitIsExhausted(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/auth/register',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    // Sign-up answers 409 for a taken address and 201 otherwise — a deliberate
    // product choice — so the endpoint can confirm whether an account exists.
    // Unmetered, that is a directory dump at request speed; the limiter is what
    // keeps the signal from being harvestable in bulk.
    $rateLimiter = $this->createRateLimiterFactory(limit: 1);

    $processor = new RegisterUserProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      requestStack: $requestStack,
      rateLimiter: $rateLimiter,
    );

    $rateLimiter->create(sprintf('registration_%s', substr(hash('sha256', '127.0.0.1'), 0, 16)))
      ->consume();

    $input = new RegisterInput();
    $input->firstName = 'Jane';
    $input->lastName = 'Doe';
    $input->email = 'jane@example.com';
    $input->password = 'Secret123!';

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process($input, new Post());
  }

  private function createRateLimiterFactory(int $limit = 100): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'registration',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
  }
}
