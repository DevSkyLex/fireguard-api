<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Console;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Domain\ValueObject\Email;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Helper\TestEventIdProvider;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};
use User\Infrastructure\Console\ListUsersCommand;

/**
 * Test ListUsersCommandTest.
 *
 * @category Console Command Tests
 */
#[CoversClass(className: ListUsersCommand::class)]
final class ListUsersCommandTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testExecuteOutputsNoUsersMessage(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new PaginatedResult(
        items: [],
        total: 0,
        limit: 50,
        offset: 0,
      ));

    $command = new ListUsersCommand(queryBus: $queryBus);
    $tester = new CommandTester($command);

    $tester->execute([]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString(
      'No users found.',
      $tester->getDisplay(),
    );
  }

  #[Test]
  public function testExecuteListsUsers(): void
  {
    $user = $this->createUser();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new PaginatedResult(
        items: [$user],
        total: 1,
        limit: 50,
        offset: 0,
      ));

    $command = new ListUsersCommand(queryBus: $queryBus);
    $tester = new CommandTester($command);

    $tester->execute([]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString(
      'Showing 1 user(s)',
      $tester->getDisplay(),
    );
  }

  private function createUser(): User
  {
    return User::register(
      id: new UserId('123e4567-e89b-12d3-a456-426614174000'),
      username: new Username('testuser'),
      email: new Email('user@example.com'),
      password: HashedPassword::fromPlain('TestPassword123!'),
      profile: new UserProfile('Test', 'User'),
      eventIdProvider: new TestEventIdProvider(),
    );
  }
  // #endregion
}
