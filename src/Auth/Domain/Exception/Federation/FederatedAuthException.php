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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class FederatedAuthException extends RuntimeException
{
  public function __construct(public readonly string $errorCode, string $message, ?Throwable $previous = null)
  {
    parent::__construct($message, previous: $previous);
  }
}
