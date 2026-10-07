<?php

declare(strict_types=1);

namespace Procurement\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Physical return evidence survives stock shortages and explicit reconciliation attempts. */
#[ORM\Entity]
#[ORM\Table(name: 'procurement_returns')]
#[ORM\Index(name: 'idx_procurement_return_org_receipt', columns: ['organization_id', 'receipt_id'])]
class ProcurementReturnRecord
{
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'receipt_id', type: 'string', length: 36)]
  public string $receiptId;

  #[ORM\Column(name: 'client_operation_id', type: 'string', length: 36)]
  public string $clientOperationId;

  #[ORM\Column(type: 'decimal', precision: 24, scale: 6)]
  public string $quantity;

  #[ORM\Column(type: 'text')]
  public string $reason;

  #[ORM\Column(name: 'actor_id', type: 'string', length: 36)]
  public string $actorId;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  #[ORM\Column(type: 'string', length: 32)]
  public string $status;

  #[ORM\Column(name: 'inventory_movement_id', type: 'string', length: 36, nullable: true)]
  public ?string $inventoryMovementId = null;

  #[ORM\Column(name: 'blocked_reason', type: 'string', length: 160, nullable: true)]
  public ?string $blockedReason = null;

  #[ORM\Column(name: 'reconciled_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $reconciledAt = null;

  #[ORM\Column(type: 'integer')]
  public int $revision;
}
