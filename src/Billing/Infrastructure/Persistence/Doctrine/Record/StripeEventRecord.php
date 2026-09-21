<?php

declare(strict_types=1);

namespace Billing\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Receipt of a committed reconciliation; no raw Stripe payload or payment data.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'billing_stripe_events')]
#[ORM\Index(name: 'idx_billing_event_org_processed', columns: ['organization_id', 'processed_at'])]
class StripeEventRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'event_id', length: 255)]
  public string $eventId;

  #[ORM\Id]
  #[ORM\Column(name: 'live_mode', type: 'boolean')]
  public bool $liveMode;

  #[ORM\Column(name: 'organization_id', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'event_type', length: 128)]
  public string $eventType;

  #[ORM\Column(name: 'event_created', type: 'integer')]
  public int $eventCreated;

  #[ORM\Column(name: 'processed_at', type: 'datetime_immutable')]
  public DateTimeImmutable $processedAt;
}
