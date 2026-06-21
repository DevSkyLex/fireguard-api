<?php

declare(strict_types=1);

namespace Billing\Application\Port\Outbound;

use Billing\Domain\Model\Subscription\Subscription;

/**
 * Port SubscriptionRepositoryPort.
 *
 * Persistence boundary for the billing subscription aggregate. There is at most
 * one subscription per organization.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface SubscriptionRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists the subscription aggregate (insert or update).
   *
   * @since 1.0.0
   *
   * @param Subscription $subscription the subscription aggregate
   */
  public function save(Subscription $subscription): void;

  /**
   * Method findByOrganizationId.
   *
   * Finds the subscription owned by an organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return ?Subscription the subscription when found
   */
  public function findByOrganizationId(string $organizationId): ?Subscription;

  /**
   * Method findByStripeCustomerId.
   *
   * Finds the subscription linked to a Stripe customer.
   *
   * @since 1.0.0
   *
   * @param string $stripeCustomerId the Stripe customer identifier
   *
   * @return ?Subscription the subscription when found
   */
  public function findByStripeCustomerId(string $stripeCustomerId): ?Subscription;
  // #endregion
}
