<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Console;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\{HelperSet, QuestionHelper};
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\{BufferedOutput, OutputInterface};
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Tester\CommandTester;
use User\Application\UseCase\Command\User\CreateUser\CreateUserCommand;
use User\Infrastructure\Console\CreateUserConsoleCommand;

use function array_shift;
use function is_string;

/**
 * Test CreateUserConsoleCommandTest.
 *
 * @category Console Command Tests
 */
#[CoversClass(className: CreateUserConsoleCommand::class)]
final class CreateUserConsoleCommandTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testExecuteDispatchesCommandWithPasswordArgument(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(function (CreateUserCommand $command): bool {
        return 'user@example.com' === $command->email
          && 'TestPassword123!' === $command->password
          && 'user@example.com' === $command->username
          && 'Test' === $command->firstName
          && 'User' === $command->lastName;
      }))
      ->willReturn($this->createMock(ResultMessage::class));

    $command = $this->createCommand($commandBus);
    $tester = new CommandTester($command);

    $tester->execute([
      'email' => 'user@example.com',
      'password' => 'TestPassword123!',
      '--first-name' => 'Test',
      '--last-name' => 'User',
    ]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString(
      'created successfully',
      $tester->getDisplay(),
    );
  }

  #[Test]
  public function testExecutePromptsForPasswordWhenMissing(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(function (CreateUserCommand $command): bool {
        return 'prompt@example.com' === $command->email
          && 'PromptPassword123!' === $command->password
          && 'prompt@example.com' === $command->username;
      }))
      ->willReturn($this->createMock(ResultMessage::class));

    $command = $this->createCommand($commandBus, [
      'PromptPassword123!',
      'PromptPassword123!',
    ]);
    $tester = new CommandTester($command);

    $tester->execute([
      'email' => 'prompt@example.com',
    ]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
  }

  #[Test]
  public function testExecuteReturnsFailureOnPasswordMismatch(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())
      ->method('dispatch');

    $command = $this->createCommand($commandBus, [
      'PromptPassword123!',
      'OtherPassword123!',
    ]);
    $tester = new CommandTester($command);

    $tester->execute([
      'email' => 'prompt@example.com',
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString(
      'Passwords do not match.',
      $tester->getDisplay(),
    );
  }

  #[Test]
  public function testExecuteReturnsFailureWhenEmailNotString(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $command = new CreateUserConsoleCommand(commandBus: $commandBus);
    $input = $this->createMock(InputInterface::class);
    $input->method('getArgument')->willReturnMap([
      ['email', null],
      ['password', null],
    ]);

    $output = new BufferedOutput();
    $exitCode = $command->run($input, $output);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Email is required.', $output->fetch());
  }

  #[Test]
  public function testExecuteRejectsInvalidPasswordThenAcceptsValid(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(function (CreateUserCommand $command): bool {
        return 'prompt@example.com' === $command->email
          && 'ValidPass123' === $command->password;
      }))
      ->willReturn($this->createMock(ResultMessage::class));

    $command = $this->createCommand($commandBus, [
      '',
      'short',
      'ValidPass123',
      'ValidPass123',
    ]);
    $tester = new CommandTester($command);

    $tester->execute([
      'email' => 'prompt@example.com',
    ]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
  }

  #[Test]
  public function testExecuteReturnsFailureWhenPasswordPromptNotString(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $command = $this->createCommand($commandBus, [null]);
    $tester = new CommandTester($command);

    $tester->execute([
      'email' => 'prompt@example.com',
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString(
      'Password is required.',
      $tester->getDisplay(),
    );
  }

  #[Test]
  public function testExecuteReturnsFailureWhenDispatchThrows(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException('boom'));

    $command = $this->createCommand($commandBus);
    $tester = new CommandTester($command);

    $tester->execute([
      'email' => 'user@example.com',
      'password' => 'TestPassword123!',
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString(
      'Failed to create user: boom',
      $tester->getDisplay(),
    );
  }

  /**
   * @param array<int, mixed> $answers
   */
  private function createCommand(CommandBusPort $commandBus, array $answers = []): CreateUserConsoleCommand
  {
    $command = new CreateUserConsoleCommand(commandBus: $commandBus);
    $command->setHelperSet(new HelperSet([
      new TestQuestionHelper($answers),
    ]));

    return $command;
  }
  // #endregion
}

final class TestQuestionHelper extends QuestionHelper
{
  /**
   * @param array<int, mixed> $answers
   */
  public function __construct(private array $answers)
  {
  }

  public function ask(InputInterface $input, OutputInterface $output, Question $question): mixed
  {
    $validator = $question->getValidator();

    while (true) {
      $answer = array_shift($this->answers);

      if (null === $validator) {
        return $answer;
      }

      if (!is_string($answer)) {
        return $answer;
      }

      try {
        return $validator($answer);
      } catch (RuntimeException $exception) {
        if ([] === $this->answers) {
          throw $exception;
        }
      }
    }
  }
}
