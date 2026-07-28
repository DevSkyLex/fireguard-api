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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid input data');

    $processor->process(new stdClass(), new Post());
  }
}
