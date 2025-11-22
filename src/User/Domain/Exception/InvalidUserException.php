<?php

declare(strict_types=1);

namespace User\Domain\Exception;

use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception InvalidUserException
 * @final
 *
 * Thrown when a user is in an invalid state for an operation.
 *
 * @category Exception
 * @package User\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidUserException extends DomainException
{
  /**
   * Method lockedAccount
   * @static
   *
   * Creates an exception for a locked account.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self The exception instance.
   */
  public static function lockedAccount(): self
  {
    return new self(message: 'User account is locked.');
  }

  /**
   * Method inactiveAccount
   * @static
   *
   * Creates an exception for an inactive account.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self The exception instance.
   */
  public static function inactiveAccount(): self
  {
    return new self(message: 'User account is inactive.');
  }

  /**
   * Method emailNotVerified
   * @static
   *
   * Creates an exception for an unverified email.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self The exception instance.
   */
  public static function emailNotVerified(): self
  {
    return new self(message: 'User email is not verified.');
  }

  /**
   * Method cannotLogin
   * @static
   *
   * Creates an exception when user cannot login.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $reason The reason why user cannot login.
   *
   * @return self The exception instance.
   */
  public static function cannotLogin(string $reason): self
  {
    return new self(message: sprintf(
      'User cannot login: %s',
      $reason
    ));
  }
}
