<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Command\Avatar\UploadUserAvatar;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Transaction\{CommitOutcomeUnknownException, TransactionAlreadyActiveException};
use Shared\Application\Port\Outbound\{FileCleanupPort, LoggerPort, RootTransactionManagerPort, UuidGeneratorPort};
use Tests\Support\Factory\UserTestFactory;
use Throwable;
use User\Application\Contract\User\AvatarPaths;
use User\Application\Port\Outbound\{AvatarPersistencePort, AvatarStoragePort};
use User\Application\Port\Outbound\Internal\UserRepositoryPort;
use User\Application\UseCase\Command\Avatar\UploadUserAvatar\{UploadUserAvatarCommand, UploadUserAvatarHandler};
use User\Domain\Exception\UserNotFoundException;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\Locale;

use function in_array;

/** @category Test */
final class UploadUserAvatarHandlerTest extends TestCase
{
  private const string ID = 'b7d21001-3a71-4c7a-9f00-000000000001';

  private const string GENERATION = 'b7d21001-3a71-4c7a-9f00-000000000002';

  public function testMissingAccountCannotWriteImages(): void
  {
    $users = $this->createStub(UserRepositoryPort::class);
    $storage = $this->createMock(AvatarStoragePort::class);
    $storage->expects(self::never())->method('resize');
    $this->expectException(UserNotFoundException::class);
    $this->handler($users, $storage)($this->command());
  }

  public function testEnclosingTransactionIsRejectedBeforeAnyImageWrite(): void
  {
    $transactions = $this->createStub(RootTransactionManagerPort::class);
    $transactions->method('assertCanStart')->willThrowException(new TransactionAlreadyActiveException());
    $storage = $this->createMock(AvatarStoragePort::class);
    $storage->expects(self::never())->method('resize');
    $this->expectException(TransactionAlreadyActiveException::class);
    $this->handler($this->createStub(UserRepositoryPort::class), $storage, transactions: $transactions)($this->command());
  }

  public function testStoresImmutableVariantsThenCommitsProfilePointerAndObsoleteCleanup(): void
  {
    $user = UserTestFactory::createActive(id: self::ID);
    $user->updateLocale(Locale::FR);
    $original = $user->profile();
    $stored = false;
    $storage = $this->createMock(AvatarStoragePort::class);
    $storage->expects(self::once())->method('resize')->with(self::ID, 'bytes', self::GENERATION)->willReturnCallback(static function () use (&$stored): void { $stored = true; });
    $storage->expects(self::never())->method('delete');
    $users = $this->createMock(UserRepositoryPort::class);
    $users->method('findById')->willReturn($user);
    $users->expects(self::once())->method('save')->willReturnCallback(static function (User $account) use (&$stored, $original): void {
      self::assertTrue($stored);
      self::assertSame($original->firstName, $account->profile()->firstName);
      self::assertSame($original->lastName, $account->profile()->lastName);
      self::assertSame(Locale::FR, $account->locale());
      self::assertSame('https://api.example.test/api/users/' . self::ID . '/avatar/256.webp?v=123', $account->profile()->avatarUrl);
    });
    $avatars = $this->createMock(AvatarPersistencePort::class);
    $avatars->method('withUserLock')->willReturnCallback(static fn (string $id, callable $operation): mixed => $operation());
    $avatars->expects(self::once())->method('replace')->with(self::ID, self::GENERATION);
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::exactly(4))->method('request')->with(self::callback(static fn (string $key): bool => in_array($key, AvatarPaths::variants(self::ID, null), true)));
    self::assertSame(self::ID, $this->handler($users, $storage, avatars: $avatars, cleanup: $cleanup)($this->command())->userId);
  }

  public function testLateSqlFailureCompensatesOnlyItsOwnGeneration(): void
  {
    $failure = new RuntimeException('late SQL failure');
    $users = $this->users();
    $users->method('save')->willThrowException($failure);
    $storage = $this->createMock(AvatarStoragePort::class);
    $storage->expects(self::once())->method('delete')->with(self::ID, self::GENERATION);

    try {
      $this->handler($users, $storage)($this->command());
      self::fail('The SQL failure must propagate.');
    } catch (Throwable $actual) {
      self::assertSame($failure, $actual);
    }
  }

  public function testCleanupAndLoggingFailuresPreserveTheOriginalSqlException(): void
  {
    $failure = new RuntimeException('primary SQL failure');
    $users = $this->users();
    $users->method('save')->willThrowException($failure);
    $storage = $this->createStub(AvatarStoragePort::class);
    $storage->method('delete')->willThrowException(new RuntimeException('cleanup failed'));
    $logger = $this->createStub(LoggerPort::class);
    $logger->method('error')->willThrowException(new RuntimeException('logging failed'));

    try {
      $this->handler($users, $storage, logger: $logger)($this->command());
      self::fail('The SQL failure must propagate.');
    } catch (Throwable $actual) {
      self::assertSame($failure, $actual);
    }
  }

  public function testAnIndeterminateCommitNeverDeletesPotentiallyWinningBytes(): void
  {
    $transactions = $this->createStub(RootTransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static function (callable $operation): never {
      $operation();

      throw new CommitOutcomeUnknownException(new RuntimeException('lost commit acknowledgement'));
    });
    $storage = $this->createMock(AvatarStoragePort::class);
    $storage->expects(self::never())->method('delete');
    $this->expectException(CommitOutcomeUnknownException::class);
    $this->handler($this->users(), $storage, transactions: $transactions)($this->command());
  }

  public function testIdentityIsRecheckedUnderTheUserLockAfterPreparingTheAttempt(): void
  {
    $users = $this->createMock(UserRepositoryPort::class);
    $users->method('findById')->willReturnOnConsecutiveCalls(UserTestFactory::createActive(id: self::ID), null);
    $users->expects(self::never())->method('save');
    $storage = $this->createMock(AvatarStoragePort::class);
    $storage->expects(self::once())->method('delete')->with(self::ID, self::GENERATION);
    $this->expectException(UserNotFoundException::class);
    $this->handler($users, $storage)($this->command());
  }

  private function command(): UploadUserAvatarCommand
  {
    return new UploadUserAvatarCommand(self::ID, 'bytes', 'https://api.example.test', 123);
  }

  private function users(): UserRepositoryPort&\PHPUnit\Framework\MockObject\Stub
  {
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findById')->willReturn(UserTestFactory::createActive(id: self::ID));

    return $users;
  }

  private function avatars(): \PHPUnit\Framework\MockObject\Stub&AvatarPersistencePort
  {
    $avatars = $this->createStub(AvatarPersistencePort::class);
    $avatars->method('withUserLock')->willReturnCallback(static fn (string $id, callable $operation): mixed => $operation());

    return $avatars;
  }

  private function handler(UserRepositoryPort $users, AvatarStoragePort $storage, ?RootTransactionManagerPort $transactions = null, ?AvatarPersistencePort $avatars = null, ?FileCleanupPort $cleanup = null, ?LoggerPort $logger = null): UploadUserAvatarHandler
  {
    if (null === $transactions) {
      $transactions = $this->createStub(RootTransactionManagerPort::class);
      $transactions->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());
    }
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::GENERATION);

    return new UploadUserAvatarHandler($users, $storage, $avatars ?? $this->avatars(), $transactions, $cleanup ?? $this->createStub(FileCleanupPort::class), $ids, $logger ?? $this->createStub(LoggerPort::class));
  }
}
