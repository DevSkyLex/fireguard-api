<?php

declare(strict_types=1);

namespace OAuth\Domain\Exception;

use Shared\Domain\Exception\DomainException;
use Throwable;

/**
 * Exception AuthorizationException
 * @final
 *
 * Thrown when an authorization error occurs.
 *
 * @category Exception
 * @package OAuth\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuthorizationException extends DomainException
{
  //#region Methods
  /**
   * Method serverError
   * @static
   *
   * Creates a server error exception (HTTP 500).
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The error message.
   * @param Throwable|null $previous The previous exception.
   *
   * @return self The exception instance.
   */
  public static function serverError(string $message, ?Throwable $previous = null): self
  {
    return new self($message, 500, $previous);
  }

  /**
   * Method invalidRequest
   * @static
   *
   * Creates an invalid request exception (HTTP 400).
   * Used when the request is missing a required parameter
   * or has an invalid parameter value.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The error message.
   * @param Throwable|null $previous The previous exception.
   *
   * @return self The exception instance.
   */
  public static function invalidRequest(string $message, ?Throwable $previous = null): self
  {
    return new self($message, 400, $previous);
  }

  /**
   * Method invalidClient
   * @static
   *
   * Creates an invalid client exception (HTTP 401).
   * Used when client authentication fails.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The error message.
   * @param Throwable|null $previous The previous exception.
   *
   * @return self The exception instance.
   */
  public static function invalidClient(string $message, ?Throwable $previous = null): self
  {
    return new self($message, 401, $previous);
  }

  /**
   * Method invalidGrant
   * @static
   *
   * Creates an invalid grant exception (HTTP 400).
   * Used when the provided authorization grant
   * or refresh token is invalid or expired.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The error message.
   * @param Throwable|null $previous The previous exception.
   *
   * @return self The exception instance.
   */
  public static function invalidGrant(string $message, ?Throwable $previous = null): self
  {
    return new self($message, 400, $previous);
  }

  /**
   * Method unauthorizedClient
   * @static
   *
   * Creates an unauthorized client exception (HTTP 400).
   * Used when the client is not authorized 
   * to use the requested grant type.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The error message.
   * @param Throwable|null $previous The previous exception.
   *
   * @return self The exception instance.
   */
  public static function unauthorizedClient(string $message, ?Throwable $previous = null): self
  {
    return new self($message, 400, $previous);
  }

  /**
   * Method unsupportedGrantType
   * @static
   *
   * Creates an unsupported grant type exception (HTTP 400).
   * Used when the authorization grant type is 
   * not supported by the server.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The error message.
   * @param Throwable|null $previous The previous exception.
   *
   * @return self The exception instance.
   */
  public static function unsupportedGrantType(string $message, ?Throwable $previous = null): self
  {
    return new self($message, 400, $previous);
  }

  /**
   * Method invalidScope
   * @static
   *
   * Creates an invalid scope exception (HTTP 400).
   * Used when the requested scope is invalid,
   * unknown, or exceeds the scope granted.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The error message.
   * @param Throwable|null $previous The previous exception.
   *
   * @return self The exception instance.
   */
  public static function invalidScope(string $message, ?Throwable $previous = null): self
  {
    return new self($message, 400, $previous);
  }

  /**
   * Method accessDenied
   * @static
   *
   * Creates an access denied exception (HTTP 403).
   * Used when the resource owner or authorization
   * server denied the request.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The error message.
   * @param Throwable|null $previous The previous exception.
   *
   * @return self The exception instance.
   */
  public static function accessDenied(string $message, ?Throwable $previous = null): self
  {
    return new self($message, 403, $previous);
  }
  //#endregion
}
