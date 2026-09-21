<?php

declare(strict_types=1);

namespace Billing\Application\Contract\Stripe;

/**
 * Current Stripe subscription, retrieved independently of a webhook snapshot.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StripeSubscription
{
  public function __construct(
    public string $id,
    public string $customerId,
    public ?string $organizationId,
    public string $status,
    public ?string $priceId,
    public ?int $currentPeriodEnd,
    public bool $cancelAtPeriodEnd,
    public int $created,
    public bool $liveMode,
  ) {
  }
}
