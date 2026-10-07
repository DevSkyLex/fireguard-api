<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Main database record. @category Record */
#[ORM\Entity]
#[ORM\Table(name:'inventory_declarations')]
#[ORM\Index(name:'idx_inventory_declarations_pending', columns:['organization_id', 'intervention_id', 'status'])]
class InventoryDeclarationRecord
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

  #[ORM\Column(name:'quantity', type:'decimal', precision:24, scale:6)]
  public string $quantity;

  #[ORM\Column(name:'intervention_id', type:'string', length:36)]
  public string $interventionId;

  #[ORM\Column(name:'work_item_id', type:'string', length:36, nullable:true)]
  public ?string $workItemId = null;

  #[ORM\Column(name:'equipment_id', type:'string', length:36, nullable:true)]
  public ?string $equipmentId = null;

  #[ORM\Column(name:'actor_id', type:'string', length:36)]
  public string $actorId;

  #[ORM\Column(name:'occurred_at', type:'datetime_immutable')]
  public DateTimeImmutable $occurredAt;

  #[ORM\Column(name:'status', type:'string', length:32)]
  public string $status;

  #[ORM\Column(name:'reason', type:'string', length:64, nullable:true)]
  public ?string $reason = null;

  #[ORM\Column(name:'movement_id', type:'string', length:36, nullable:true)]
  public ?string $movementId = null;

  #[ORM\Column(name:'late', type:'boolean')]
  public bool $late = false;
}
