<?php

declare(strict_types=1);

namespace Billing\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record SubscriptionRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'billing_subscriptions')]
#[ORM\UniqueConstraint(name: 'uniq_billing_subscription_org', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_billing_subscription_customer', columns: ['stripe_customer_id'])]
#[ORM\Index(name: 'idx_billing_subscription_stripe', columns: ['stripe_subscription_id'])]
class SubscriptionRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property stripeCustomerId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'stripe_customer_id', type: 'string', length: 255)]
  public string $stripeCustomerId;

  /**
   * Property stripeSubscriptionId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'stripe_subscription_id', type: 'string', length: 255, nullable: true)]
  public ?string $stripeSubscriptionId = null;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'status', type: 'string', length: 40)]
  public string $status;

  /**
   * Property planKey.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'plan_key', type: 'string', length: 60, nullable: true)]
  public ?string $planKey = null;

  /**
   * Property interval.
   *
   * Persisted under `billing_interval` to avoid the reserved SQL word.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'billing_interval', type: 'string', length: 10, nullable: true)]
  public ?string $interval = null;

  /**
   * Property currentPeriodEnd.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'current_period_end', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $currentPeriodEnd = null;

  /**
   * Property cancelAtPeriodEnd.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'cancel_at_period_end', type: 'boolean')]
  public bool $cancelAtPeriodEnd = false;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
  // #endregion
}
