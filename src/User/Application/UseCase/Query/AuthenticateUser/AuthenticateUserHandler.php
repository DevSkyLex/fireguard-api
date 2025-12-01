<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\AuthenticateUser;

use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Exception\InvalidPasswordException;
use User\Domain\Exception\InvalidUserException;
use User\Domain\Exception\UserNotFoundException;
use Shared\Domain\ValueObject\Email;
use User\Domain\ValueObject\Username;

/**
 * Handler AuthenticateUserHandler
 * @final
 *
 * Handles user authentication.
 *
 * @category Handler
 * @package User\Application\UseCase\Query\AuthenticateUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthenticateUserHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the AuthenticateUserHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository The user repository.
   */
  public function __construct(
    private readonly UserRepositoryPort $userRepository,
  ) {
  }
  //#endregion

  /**
   * Method __invoke
   *
   * Handles the AuthenticateUserQuery.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AuthenticateUserQuery $query The query.
   *
   * @return AuthenticateUserResult The result.
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
      if ($user === null) {
        $username = new Username(value: $query->username);
        $user = $this->userRepository->findByUsername(username: $username);
      }

      if ($user === null)
        throw UserNotFoundException::withUsername(
          username: $query->username
        );

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
    } catch (UserNotFoundException | InvalidUserException | InvalidPasswordException) {
      // Return failure (don't expose which part failed for security)
      return new AuthenticateUserResult(authenticated: false);
    }
  }
}
