<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Contract\User\UserView;
use User\Application\Port\Inbound\UserDirectoryPort;
use User\Application\Port\Outbound\Internal\UserRepositoryPort;
use User\Application\Service\UserCredentialsService;
use User\Domain\ValueObject\UserStatus;

/**
 * Class UserCredentialsServiceTest.
 *
 * Verifies account refusal order and durable failed-password accounting.
 *
 * @category Service Tests
 */
#[CoversClass(UserCredentialsService::class)]
final class UserCredentialsServiceTest extends TestCase
{
  // #region Properties
  /**
   * Constant USER_ID.
   */
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';
  // #endregion

  // #region Methods
  /**
   * Method testMissingAccountDoesNotWriteOrResolveAProjection.
   */
  public function testMissingAccountDoesNotWriteOrResolveAProjection(): void
  {
    $users = $this->createMock(UserRepositoryPort::class);
    $users->method('findById')->willReturn(null);
    $users->expects(self::never())->method('save');
    $directory = $this->createMock(UserDirectoryPort::class);
    $directory->expects(self::never())->method('findById');

    $result = new UserCredentialsService($users, $directory)->authenticate(self::USER_ID, 'wrong');

    self::assertSame('missing', $result->status);
    self::assertNull($result->user);
  }

  /**
   * Method testUnavailableAccountIsRefusedBeforePasswordAccounting.
   */
  public function testUnavailableAccountIsRefusedBeforePasswordAccounting(): void
  {
    $user = UserTestFactory::createPending(id: self::USER_ID);
    $users = $this->createMock(UserRepositoryPort::class);
    $users->method('findById')->willReturn($user);
    $users->expects(self::never())->method('save');
    $directory = $this->createMock(UserDirectoryPort::class);
    $directory->expects(self::never())->method('findById');

    $result = new UserCredentialsService($users, $directory)->authenticate(self::USER_ID, 'wrong');

    self::assertSame('unavailable', $result->status);
    self::assertNull($result->user);
    self::assertSame(0, $user->failedLoginAttempts());
  }

  /**
   * Method testWrongPasswordPersistsTheLockBeforeRefusingLaterAttempts.
   */
  public function testWrongPasswordPersistsTheLockBeforeRefusingLaterAttempts(): void
  {
    $user = UserTestFactory::createActive(id: self::USER_ID);
    $users = $this->createMock(UserRepositoryPort::class);
    $users->method('findById')->willReturn($user);
    $users->expects(self::exactly(5))->method('save')->with($user);
    $directory = $this->createMock(UserDirectoryPort::class);
    $directory->expects(self::never())->method('findById');
    $service = new UserCredentialsService($users, $directory);
    for ($attempt = 1; $attempt <= 5; ++$attempt) {
      $result = $service->authenticate(self::USER_ID, 'wrong');
      self::assertSame('invalid_password', $result->status);
      self::assertNull($result->user);
      self::assertSame($attempt, $user->failedLoginAttempts());
    }

    self::assertSame(UserStatus::LOCKED, $user->status());
    self::assertSame('unavailable', $service->authenticate(self::USER_ID, 'password123')->status);
    self::assertSame(5, $user->failedLoginAttempts());
  }

  /**
   * Method testSuccessPersistsResetAccountingBeforeReadingTheProjection.
   */
  public function testSuccessPersistsResetAccountingBeforeReadingTheProjection(): void
  {
    $user = UserTestFactory::createActive(id: self::USER_ID);
    $user->recordFailedLogin();
    $view = UserTestFactory::view($user);
    $users = $this->createMock(UserRepositoryPort::class);
    $users->method('findById')->willReturn($user);
    $saved = false;
    $users->expects(self::once())->method('save')->with($user)->willReturnCallback(static function () use (&$saved): void { $saved = true; });
    $directory = $this->createMock(UserDirectoryPort::class);
    $directory->expects(self::once())->method('findById')->with(self::USER_ID)->willReturnCallback(static function () use (&$saved, $view): UserView {
      self::assertTrue($saved);

      return $view;
    });

    $result = new UserCredentialsService($users, $directory)->authenticate(self::USER_ID, 'password123');

    self::assertSame('authenticated', $result->status);
    self::assertSame($view, $result->user);
    self::assertSame(0, $user->failedLoginAttempts());
    self::assertNotNull($user->lastLoginAt());
  }

  /**
   * Method testAccountingPersistenceFailureRemainsATechnicalFailure.
   */
  public function testAccountingPersistenceFailureRemainsATechnicalFailure(): void
  {
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findById')->willReturn(UserTestFactory::createActive(id: self::USER_ID));
    $users->method('save')->willThrowException(new RuntimeException('accounting unavailable'));
    $directory = $this->createMock(UserDirectoryPort::class);
    $directory->expects(self::never())->method('findById');

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('accounting unavailable');
    new UserCredentialsService($users, $directory)->authenticate(self::USER_ID, 'wrong');
  }
  // #endregion
}
