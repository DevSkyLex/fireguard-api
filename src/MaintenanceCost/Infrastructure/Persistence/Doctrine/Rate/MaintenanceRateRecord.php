<?php

declare(strict_types=1);

namespace MaintenanceCost\Infrastructure\Persistence\Doctrine\Rate;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Append-only rate parameters use the organization's common currency until its first capture. */
#[ORM\Entity]
#[ORM\Table(name: 'maintenance_cost_hourly_rates')]
#[ORM\UniqueConstraint(name: 'uniq_maintenance_cost_rate_client', columns: ['organization_id', 'client_id'])]
#[ORM\UniqueConstraint(name: 'uniq_maintenance_cost_rate_effective', columns: ['organization_id', 'member_id', 'effective_from'])]
#[ORM\Index(name: 'idx_maintenance_cost_rate_member', columns: ['organization_id', 'member_id'])]
class MaintenanceRateRecord
{
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'member_id', type: 'string', length: 36)]
  public string $memberId;

  #[ORM\Column(name: 'client_id', type: 'string', length: 36)]
  public string $clientId;

  #[ORM\Column(name: 'hourly_amount', type: 'decimal', precision: 24, scale: 6)]
  public string $hourlyAmount;

  #[ORM\Column(name: 'effective_from', type: 'date_immutable')]
  public DateTimeImmutable $effectiveFrom;
}
