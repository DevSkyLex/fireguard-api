<?php

declare(strict_types=1);

namespace Auth\Domain\Exception;

use Shared\Domain\Exception\DomainException;

/**
 * Exception AuthorizationException
 * @final
 *
 * Exception thrown when OAuth2 authorization fails.
 *
 * @category Exception
 * @package Auth\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuthorizationException extends DomainException
{
  //#region Factory Methods
  /**
   * Method invalidClient
   * @static
   *
   * Creates an exception for invalid client credentials.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self The exception.
   */
  public static function invalidClient(): self
  {
    return new self(message: 'Invalid client credentials.');
  }

  /**
   * Method invalidGrant
   * @static
   *
   * Creates an exception for invalid grant.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $reason The reason.
   *
   * @return self The exception.
   */
  public static function invalidGrant(string $reason): self
  {
    return new self(message: sprintf('Invalid grant: %s', $reason));
  }

  /**
   * Method invalidScope
   * @static
   *
   * Creates an exception for invalid scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self The exception.
   */
  public static function invalidScope(): self
  {
    return new self(message: 'Invalid scope requested.');
  }

  /**
   * Method serverError
   * @static
   *
   * Creates an exception for server error.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The error message.
   *
   * @return self The exception.
   */
  public static function serverError(string $message): self
  {
    return new self(message: sprintf('Authorization server error: %s', $message));
  }
  //#endregion
}
