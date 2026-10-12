<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Query\User\AuthenticateUser;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\Internal\UserRepositoryPort;
use User\Application\UseCase\Query\User\AuthenticateUser\{AuthenticateUserHandler, AuthenticateUserQuery};
use User\Domain\ValueObject\UserStatus;

/**
 * Class AuthenticationVerificationPolicyTest
 *
 * Proves verification waivers do not mark mailbox proof or bypass lockout and disabled states.
 *
 * @category Handler Tests
 */
#[CoversClass(AuthenticateUserHandler::class)]
final class AuthenticationVerificationPolicyTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testWaiverAllowsExistingPendingAccountAfterValidCredentials(): void
  {
    $user = UserTestFactory::createPending();
    $users = $this->createMock(UserRepositoryPort::class);
    $users->method('findByEmail')->willReturn($user);
    $users->expects(self::once())->method('save')->with($user);
    $result = new AuthenticateUserHandler($users)(new AuthenticateUserQuery('test@example.com', 'password123', false));

    self::assertTrue($result->authenticated);
    self::assertSame(UserStatus::ACTIVE, $user->status());
    self::assertFalse($user->isEmailVerified());
    self::assertNull($user->emailOwnershipVerifiedAt());
  }

  #[Test]
  public function testWaiverCannotAuthenticateLockedAccount(): void
  {
    $user = UserTestFactory::createActive();
    for ($attempt = 0; $attempt < 5; ++$attempt) {
      $user->recordFailedLogin();
    }
    $users = $this->createMock(UserRepositoryPort::class);
    $users->method('findByEmail')->willReturn($user);
    $users->expects(self::never())->method('save');
    $result = new AuthenticateUserHandler($users)(new AuthenticateUserQuery('test@example.com', 'password123', false));

    self::assertFalse($result->authenticated);
    self::assertSame(UserStatus::LOCKED, $user->status());
  }

  #[Test]
  public function testWaiverCannotAuthenticateDisabledAccount(): void
  {
    $user = UserTestFactory::createActive();
    $user->deactivate(new \Tests\Helper\TestEventIdProvider());
    $users = $this->createMock(UserRepositoryPort::class);
    $users->method('findByEmail')->willReturn($user);
    $users->expects(self::never())->method('save');
    $result = new AuthenticateUserHandler($users)(new AuthenticateUserQuery('test@example.com', 'password123', false));

    self::assertFalse($result->authenticated);
    self::assertSame(UserStatus::INACTIVE, $user->status());
  }
  // #endregion
}
