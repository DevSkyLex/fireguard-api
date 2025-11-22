<?php

declare(strict_types=1);

namespace User\Domain\Exception;

use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception UserAlreadyExistsException
 * @final
 *
 * Thrown when attempting to create a user that already exists.
 *
 * @category Exception
 * @package User\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserAlreadyExistsException extends DomainException
{
  //#region Methods
  /**
   * Method withUsername
   * @static
   *
   * Creates an exception for duplicate username.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $username The duplicate username.
   *
   * @return self The exception instance.
   */
  public static function withUsername(string $username): self
  {
    return new self(message: sprintf(
      'User with username "%s" already exists.',
      $username
    ));
  }

  /**
   * Method withEmail
   * @static
   *
   * Creates an exception for duplicate email.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $email The duplicate email.
   *
   * @return self The exception instance.
   */
  public static function withEmail(string $email): self
  {
    return new self(message: sprintf(
      'User with email "%s" already exists.',
      $email
    ));
  }
  //#endregion
}
