<?php

declare(strict_types=1);

namespace Procurement\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Main-database record retaining procurement evidence without historical deletion. */
#[ORM\Entity]
#[ORM\Table(name: 'procurement_receipts')]
#[ORM\Index(name: 'idx_procurement_receipt_org_order', columns: ['organization_id', 'order_id'])]
class ProcurementReceiptRecord
{
  #[ORM\Column(name: 'pending_return_quantity', type: 'decimal', precision: 24, scale: 6, options: ['default' => '0.000000'])]
  public string $pendingReturnQuantity = '0.000000';

  #[ORM\Id]
  #[ORM\Column(name: 'id', type: 'string', length: 36)]
  public string $id;

  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'order_id', type: 'string', length: 36)]
  public string $orderId;

  #[ORM\Column(name: 'line_id', type: 'string', length: 36)]
  public string $lineId;

  #[ORM\Column(name: 'kind', type: 'string', length: 32)]
  public string $kind;

  #[ORM\Column(name: 'quantity', type: 'decimal', precision: 24, scale: 6)]
  public string $quantity;

  #[ORM\Column(name: 'warehouse_id', type: 'string', length: 36, nullable: true)]
  public ?string $warehouseId = null;

  #[ORM\Column(name: 'unit_cost', type: 'decimal', precision: 24, scale: 6, nullable: true)]
  public ?string $unitCost = null;

  #[ORM\Column(name: 'currency', type: 'string', length: 3)]
  public string $currency;

  #[ORM\Column(name: 'received_at', type: 'datetime_immutable')]
  public DateTimeImmutable $receivedAt;

  #[ORM\Column(name: 'actor_id', type: 'string', length: 36)]
  public string $actorId;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  #[ORM\Column(name: 'inventory_movement_id', type: 'string', length: 36, nullable: true)]
  public ?string $inventoryMovementId = null;

  /**
   * @var array<array-key,mixed>
   */
  #[ORM\Column(name: 'equipment_ids', type: 'json', options: ['jsonb' => true])]
  public array $equipmentIds;

  #[ORM\Column(name: 'returned_quantity', type: 'decimal', precision: 24, scale: 6)]
  public string $returnedQuantity;

  #[ORM\Column(name: 'blocked_reason', type: 'string', length: 160, nullable: true)]
  public ?string $blockedReason = null;

  #[ORM\Column(name: 'revision', type: 'integer')]
  public int $revision;
}
