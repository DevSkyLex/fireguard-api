<?php

declare(strict_types=1);

namespace Billing\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * Exception InvalidWebhookSignatureException.
 *
 * Raised when an incoming Stripe webhook payload fails signature verification.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidWebhookSignatureException extends RuntimeException
{
  // #region Methods
  /**
   * Method create.
   *
   * Creates the exception with an optional underlying cause.
   *
   * @since 1.0.0
   *
   * @param ?Throwable $previous the underlying error, when any
   *
   * @return self the exception instance
   */
  public static function create(?Throwable $previous = null): self
  {
    return new self('Invalid Stripe webhook signature.', 0, $previous);
  }
  // #endregion
}
