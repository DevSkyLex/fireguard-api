<?php

declare(strict_types=1);

namespace Billing\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception NoActiveSubscriptionException.
 *
 * Raised when an operation requires a live Stripe subscription for an
 * organization (e.g. scheduling or resuming a cancellation) but none exists.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NoActiveSubscriptionException extends RuntimeException
{
  // #region Methods
  /**
   * Method forOrganization.
   *
   * Creates the exception for an organization without a live subscription.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return self the exception instance
   */
  public static function forOrganization(string $organizationId): self
  {
    return new self(sprintf('No active subscription exists for organization "%s".', $organizationId));
  }
  // #endregion
}
