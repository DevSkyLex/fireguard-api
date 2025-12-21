<?php

declare(strict_types=1);

namespace User\Domain\Exception;

use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception InvalidUserException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidUserException extends DomainException
{
  /**
   * Method lockedAccount.
   *
   * @static
   *
   * Creates an exception for a locked account.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function lockedAccount(): self
  {
    return new self(message: 'User account is locked.');
  }

  /**
   * Method inactiveAccount.
   *
   * @static
   *
   * Creates an exception for an inactive account.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function inactiveAccount(): self
  {
    return new self(message: 'User account is inactive.');
  }

  /**
   * Method emailNotVerified.
   *
   * @static
   *
   * Creates an exception for an unverified email.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function emailNotVerified(): self
  {
    return new self(message: 'User email is not verified.');
  }

  /**
   * Method cannotLogin.
   *
   * @static
   *
   * Creates an exception when user cannot login.
   *
   * @since 1.0.0
   *
   * @param string $reason the reason why user cannot login
   *
   * @return self the exception instance
   */
  public static function cannotLogin(string $reason): self
  {
    return new self(message: sprintf(
      'User cannot login: %s',
      $reason,
    ));
  }
}
