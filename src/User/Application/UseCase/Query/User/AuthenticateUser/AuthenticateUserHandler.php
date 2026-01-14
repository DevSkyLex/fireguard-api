<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\User\AuthenticateUser;

use Shared\Domain\ValueObject\Email;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Exception\{InvalidPasswordException, InvalidUserException, UserNotFoundException};
use User\Domain\ValueObject\Username;

use function filter_var;

use const FILTER_VALIDATE_EMAIL;

/**
 * Handler AuthenticateUserHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthenticateUserHandler implements \Shared\Application\Message\QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the AuthenticateUserHandler class.
   *
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository the user repository
   */
  public function __construct(
    private readonly UserRepositoryPort $userRepository,
  ) {
  }
  // #endregion

  /**
   * Method __invoke.
   *
   * Handles the AuthenticateUserQuery.
   *
   * @since 1.0.0
   *
   * @param AuthenticateUserQuery $query the query
   *
   * @return AuthenticateUserResult the result
   */
  public function __invoke(AuthenticateUserQuery $query): AuthenticateUserResult
  {
    try {
      // Try to find user by email first, then by username
      $user = null;

      // Check if input looks like an email
      if (filter_var($query->username, FILTER_VALIDATE_EMAIL)) {
        $email = new Email(value: $query->username);
        $user = $this->userRepository->findByEmail(email: $email);
      }

      // If not found by email, try by username
      if (null === $user) {
        $username = new Username(value: $query->username);
        $user = $this->userRepository->findByUsername(username: $username);
      }

      if (null === $user) {
        throw UserNotFoundException::withUsername(
          username: $query->username,
        );
      }

      // Authenticate
      $user->authenticate(plainPassword: $query->password);

      // Update last login
      $this->userRepository->save(user: $user);

      // Return success
      return new AuthenticateUserResult(
        authenticated: true,
        userId: $user->id()->value,
        email: $user->email()->value,
        fullName: $user->profile()->fullName(),
      );
    } catch (UserNotFoundException|InvalidUserException|InvalidPasswordException) {
      // Return failure (don't expose which part failed for security)
      return new AuthenticateUserResult(authenticated: false);
    }
  }
}
