<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Main database record. @category Record */
#[ORM\Entity]
#[ORM\Table(name:'inventory_movements')]
#[ORM\Index(name:'idx_inventory_movements_intervention', columns:['organization_id', 'intervention_id'])]
#[ORM\Index(name:'idx_inventory_movements_correction', columns:['organization_id', 'correction_of'])]
class InventoryMovementRecord
{
  #[ORM\Id]
  #[ORM\Column(name:'id', type:'string', length:36)]
  public string $id;

  #[ORM\Column(name:'organization_id', type:'string', length:36)]
  public string $organizationId;

  #[ORM\Column(name:'warehouse_id', type:'string', length:36)]
  public string $warehouseId;

  #[ORM\Column(name:'part_id', type:'string', length:36)]
  public string $partId;

  #[ORM\Column(name:'kind', type:'string', length:32)]
  public string $kind;

  #[ORM\Column(name:'quantity', type:'decimal', precision:24, scale:6)]
  public string $quantity;

  #[ORM\Column(name:'unit_cost', type:'decimal', precision:24, scale:6, nullable:true)]
  public ?string $unitCost = null;

  #[ORM\Column(name:'total_value', type:'decimal', precision:24, scale:6, nullable:true)]
  public ?string $totalValue = null;

  #[ORM\Column(name:'currency', type:'string', length:3)]
  public string $currency;

  #[ORM\Column(name:'reason', type:'text')]
  public string $reason;

  #[ORM\Column(name:'actor_id', type:'string', length:36)]
  public string $actorId;

  #[ORM\Column(name:'occurred_at', type:'datetime_immutable')]
  public DateTimeImmutable $occurredAt;

  #[ORM\Column(name:'intervention_id', type:'string', length:36, nullable:true)]
  public ?string $interventionId = null;

  #[ORM\Column(name:'work_item_id', type:'string', length:36, nullable:true)]
  public ?string $workItemId = null;

  #[ORM\Column(name:'equipment_id', type:'string', length:36, nullable:true)]
  public ?string $equipmentId = null;

  #[ORM\Column(name:'correction_of', type:'string', length:36, nullable:true)]
  public ?string $correctionOf = null;

  #[ORM\Column(name:'source_receipt_id', type:'string', length:36, nullable:true)]
  public ?string $sourceReceiptId = null;

  #[ORM\Column(name:'late', type:'boolean')]
  public bool $late = false;
}
