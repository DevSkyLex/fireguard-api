<?php

declare(strict_types=1);

namespace User\Domain\Exception;

use Shared\Domain\Exception\EntityNotFoundException;

use function sprintf;

/**
 * Exception UserNotFoundException
 * @final
 *
 * Thrown when a user cannot be found.
 *
 * @category Exception
 * @package User\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserNotFoundException extends EntityNotFoundException
{
  //#region Methods
  /**
   * Method withId
   * @static
   *
   * Creates an exception for user not 
   * found by ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $id The user ID.
   *
   * @return self The exception instance.
   */
  public static function withId(string $id): self
  {
    return new self(message: sprintf(
      'User with ID "%s" not found.',
      $id
    ));
  }

  /**
   * Method withUsername
   * @static
   *
   * Creates an exception for user not 
   * found by username.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $username The username.
   *
   * @return self The exception instance.
   */
  public static function withUsername(string $username): self
  {
    return new self(message: sprintf(
      'User with username "%s" not found.',
      $username
    ));
  }

  /**
   * Method withEmail
   * @static
   *
   * Creates an exception for user not 
   * found by email.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $email The email.
   *
   * @return self The exception instance.
   */
  public static function withEmail(string $email): self
  {
    return new self(message: sprintf(
      'User with email "%s" not found.',
      $email
    ));
  }
  //#endregion
}
