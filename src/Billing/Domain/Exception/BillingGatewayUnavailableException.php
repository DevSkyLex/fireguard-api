<?php

declare(strict_types=1);

namespace Billing\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * Exception BillingGatewayUnavailableException.
 *
 * Raised when the Stripe API cannot be reached, or returns an error, while
 * reading billing data (e.g. the organization's saved payment method). Lets
 * the presentation layer degrade to a clear "service unavailable" response
 * instead of a raw 500.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class BillingGatewayUnavailableException extends RuntimeException
{
  // #region Methods
  /**
   * Method create.
   *
   * Creates the exception, wrapping the underlying Stripe SDK failure.
   *
   * @since 1.0.0
   *
   * @param ?Throwable $previous the underlying error, when any
   *
   * @return self the exception instance
   */
  public static function create(?Throwable $previous = null): self
  {
    return new self('The billing gateway is temporarily unavailable.', 0, $previous);
  }
  // #endregion
}
