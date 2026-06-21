<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Billing\Presentation\Api\Serialization\BillingSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO SubscriptionOutput.
 *
 * Current billing subscription state of an organization, used to render the
 * subscription panel (status badge, renewal date, scheduled cancellation).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SubscriptionOutput
{
  // #region Properties
  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $organizationId = '';

  /**
   * Property hasSubscription.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $hasSubscription = false;

  /**
   * Property active.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $active = false;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $status = null;

  /**
   * Property planKey.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $planKey = null;

  /**
   * Property interval.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $interval = null;

  /**
   * Property currentPeriodEnd.
   *
   * ISO 8601 timestamp of the end of the current billing period, or null.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $currentPeriodEnd = null;

  /**
   * Property cancelAtPeriodEnd.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $cancelAtPeriodEnd = false;
  // #endregion
}
