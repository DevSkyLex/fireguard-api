<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/** Main database record. @category Record */
#[ORM\Entity]
#[ORM\Table(name:'inventory_warehouses')]
#[ORM\UniqueConstraint(name:'uniq_inventory_warehouses_code', columns:['organization_id', 'code'])]
class InventoryWarehouseRecord
{
  #[ORM\Id]
  #[ORM\Column(name:'id', type:'string', length:36)]
  public string $id;

  #[ORM\Column(name:'organization_id', type:'string', length:36)]
  public string $organizationId;

  #[ORM\Column(name:'code', type:'string', length:100)]
  public string $code;

  #[ORM\Column(name:'label', type:'string', length:255)]
  public string $label;

  #[ORM\Column(name:'archived', type:'boolean')]
  public bool $archived = false;
}
