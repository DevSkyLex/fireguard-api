<?php

declare(strict_types=1);

namespace Tests\Support\Factory;

use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

use function password_hash;

use const PASSWORD_BCRYPT;

/**
 * Class UserTestFactory.
 *
 * Factory for creating User instances in tests.
 * Avoids mocking final readonly classes.
 *
 * @category Test Support
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserTestFactory
{
  // #region Constants
  private const string DEFAULT_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string DEFAULT_USERNAME = 'testuser';

  private const string DEFAULT_EMAIL = 'test@example.com';

  private const string DEFAULT_PASSWORD = 'password123';

  private const string DEFAULT_FIRST_NAME = 'Test';

  private const string DEFAULT_LAST_NAME = 'User';
  // #endregion

  // #region Methods
  /**
   * Create a pending verification user.
   *
   * @param string|null $id user ID (UUID format)
   * @param string|null $email user email
   * @param string|null $username username
   *
   * @return User the created user
   */
  public static function createPending(
    ?string $id = null,
    ?string $email = null,
    ?string $username = null,
  ): User {
    $eventIdProvider = new TestEventIdProvider();

    return User::register(
      id: new UserId($id ?? self::DEFAULT_UUID),
      username: new Username($username ?? self::DEFAULT_USERNAME),
      email: new Email($email ?? self::DEFAULT_EMAIL),
      password: new HashedPassword(password_hash(self::DEFAULT_PASSWORD, PASSWORD_BCRYPT)),
      profile: new UserProfile(self::DEFAULT_FIRST_NAME, self::DEFAULT_LAST_NAME),
      eventIdProvider: $eventIdProvider,
    );
  }

  /**
   * Create an active (verified) user.
   *
   * @param string|null $id user ID (UUID format)
   * @param string|null $email user email
   * @param string|null $username username
   *
   * @return User the created and activated user
   */
  public static function createActive(
    ?string $id = null,
    ?string $email = null,
    ?string $username = null,
  ): User {
    $user = self::createPending($id, $email, $username);
    $user->verifyEmail(new TestEventIdProvider());

    return $user;
  }

  // #endregion
}
