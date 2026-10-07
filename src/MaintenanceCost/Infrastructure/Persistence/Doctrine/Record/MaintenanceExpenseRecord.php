<?php

declare(strict_types=1);

namespace MaintenanceCost\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Class MaintenanceExpenseRecord. Append-only exact external expenses. @category Record */
#[ORM\Entity]
#[ORM\Table(name: 'maintenance_cost_expenses')]
#[ORM\UniqueConstraint(name: 'uniq_maintenance_expense_client', columns: ['organization_id', 'client_id'])]
#[ORM\Index(name: 'idx_maintenance_expense_intervention', columns: ['organization_id', 'intervention_id'])]
class MaintenanceExpenseRecord
{
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  #[ORM\Column(name: 'organization_id', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'intervention_id', length: 36)]
  public string $interventionId;

  #[ORM\Column(name: 'work_item_id', length: 36, nullable: true)]
  public ?string $workItemId = null;

  #[ORM\Column(name: 'client_id', length: 64)]
  public string $clientId;

  #[ORM\Column(type: 'decimal', precision: 24, scale: 6)]
  public string $amount;

  #[ORM\Column(length: 3)]
  public string $currency;

  #[ORM\Column(type: 'text')]
  public string $description;

  #[ORM\Column(name: 'incurred_at', type: 'datetime_immutable')]
  public DateTimeImmutable $incurredAt;

  #[ORM\Column(name: 'adjustment_of', length: 36, nullable: true)]
  public ?string $adjustmentOf = null;

  #[ORM\Column(name: 'created_by', length: 36)]
  public string $createdBy;

  #[ORM\Column(name: 'payload_hash', length: 64)]
  public string $payloadHash;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;
}
