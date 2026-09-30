<?php

declare(strict_types=1);

namespace Auth\Domain\Exception\Federation;

use RuntimeException;
use Throwable;

/**
 * Exception FederatedAuthException.
 *
 * Carries a stable public error code without exposing provider responses.
 *
 * @category Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class FederatedAuthException extends RuntimeException
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries a stable federated-authentication error code and its causal exception.
   *
   * @access public
   *
   * @param string $errorCode stable machine-readable code exposed for this authentication failure
   * @param string $message safe human-readable explanation passed to the base exception
   * @param ?Throwable $previous underlying cause, when the failure wraps another exception
   *
   * @return void
   */
  public function __construct(public readonly string $errorCode, string $message, ?Throwable $previous = null)
  {
    parent::__construct($message, previous: $previous);
  }
  // #endregion
}
