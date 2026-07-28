<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Console;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\ValueObject\Email;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Application\UseCase\Command\User\DeleteUser\DeleteUserCommand;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};
use User\Infrastructure\Console\DeleteUserConsoleCommand;

use function preg_replace;

/**
 * Test DeleteUserConsoleCommandTest.
 *
 * The command accepts either a UUID or an email address, so both resolution
 * branches have to reach the bus with the SAME user id — and an unknown
 * identifier must fail loudly instead of dispatching a delete for nothing.
 *
 * @category Console Command Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DeleteUserConsoleCommand::class)]
final class DeleteUserConsoleCommandTest extends TestCase
{
  private const string USER_ID = '019c6649-a426-7960-b604-20da6359a2fa';

  private const string USER_EMAIL = 'jdoe@example.com';

  // #region Methods
  #[Test]
  public function testExecuteDeletesAUserResolvedByItsId(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (DeleteUserCommand $command): bool => self::USER_ID === $command->id))
      ->willReturn($this->createStub(ResultMessage::class));

    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($this->makeUser());
    $repository->expects(self::never())->method('findByEmail');

    $tester = new CommandTester(new DeleteUserConsoleCommand($commandBus, $repository));

    $tester->execute(['identifier' => self::USER_ID, '--force' => true]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    // assertStringContainsString on the raw display is fragile: SymfonyStyle
    // wraps the success block to the terminal width, which can split
    // "deleted successfully" across two lines depending on the runner.
    $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
    self::assertStringContainsString('deleted successfully', (string) $display);
  }

  #[Test]
  public function testExecuteDeletesAUserResolvedByItsEmail(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (DeleteUserCommand $command): bool => self::USER_ID === $command->id))
      ->willReturn($this->createStub(ResultMessage::class));

    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByEmail')
      ->with(self::callback(static fn (Email $email): bool => self::USER_EMAIL === (string) $email))
      ->willReturn($this->makeUser());
    $repository->expects(self::never())->method('findById');

    $tester = new CommandTester(new DeleteUserConsoleCommand($commandBus, $repository));

    $tester->execute(['identifier' => self::USER_EMAIL, '--force' => true]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
  }

  #[Test]
  public function testExecuteFailsWhenNoUserMatchesTheId(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $repository = $this->createStub(UserRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $tester = new CommandTester(new DeleteUserConsoleCommand($commandBus, $repository));

    $tester->execute(['identifier' => self::USER_ID, '--force' => true]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('not found', $tester->getDisplay());
  }

  #[Test]
  public function testExecuteFailsWhenNoUserMatchesTheEmail(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $repository = $this->createStub(UserRepositoryPort::class);
    $repository->method('findByEmail')->willReturn(null);

    $tester = new CommandTester(new DeleteUserConsoleCommand($commandBus, $repository));

    $tester->execute(['identifier' => self::USER_EMAIL, '--force' => true]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('not found', $tester->getDisplay());
  }

  #[Test]
  public function testExecuteAsksForConfirmationAndCancelsOnRefusal(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $repository = $this->createStub(UserRepositoryPort::class);
    $repository->method('findById')->willReturn($this->makeUser());

    $tester = new CommandTester(new DeleteUserConsoleCommand($commandBus, $repository));
    $tester->setInputs(['no']);

    $tester->execute(['identifier' => self::USER_ID], ['interactive' => true]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString('Deletion cancelled.', $tester->getDisplay());
  }

  #[Test]
  public function testExecuteDeletesAfterAConfirmedPrompt(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn($this->createStub(ResultMessage::class));

    $repository = $this->createStub(UserRepositoryPort::class);
    $repository->method('findById')->willReturn($this->makeUser());

    $tester = new CommandTester(new DeleteUserConsoleCommand($commandBus, $repository));
    $tester->setInputs(['yes']);

    $tester->execute(['identifier' => self::USER_ID], ['interactive' => true]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
  }

  #[Test]
  public function testExecuteFailsWhenTheIdentifierIsBlank(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $command = new DeleteUserConsoleCommand($commandBus, $this->createStub(UserRepositoryPort::class));

    $input = $this->createStub(InputInterface::class);
    $input->method('getArgument')->willReturn('   ');
    $input->method('getOption')->willReturn(false);

    $output = new BufferedOutput();

    self::assertSame(Command::FAILURE, $command->run($input, $output));
    self::assertStringContainsString('Identifier is required', $output->fetch());
  }

  #[Test]
  public function testExecuteFailsWhenTheBusRejectsTheDeletion(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new RuntimeException('boom'));

    $repository = $this->createStub(UserRepositoryPort::class);
    $repository->method('findById')->willReturn($this->makeUser());

    $tester = new CommandTester(new DeleteUserConsoleCommand($commandBus, $repository));

    $tester->execute(['identifier' => self::USER_ID, '--force' => true]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('Failed to delete user: boom', $tester->getDisplay());
  }

  private function makeUser(): User
  {
    return User::register(
      id: new UserId(self::USER_ID),
      username: new Username('jdoe'),
      email: new Email(self::USER_EMAIL),
      password: HashedPassword::fromPlain('P@ssw0rd123'),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: new TestEventIdProvider(),
    );
  }
  // #endregion
}
