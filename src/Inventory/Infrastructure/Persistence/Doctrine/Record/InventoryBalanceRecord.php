<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/** Main database record. @category Record */
#[ORM\Entity]
#[ORM\Table(name:'inventory_balances')]
#[ORM\UniqueConstraint(name:'uniq_inventory_balances_scope', columns:['organization_id', 'warehouse_id', 'part_id'])]
class InventoryBalanceRecord
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

  #[ORM\Column(name:'total_value', type:'decimal', precision:24, scale:6, nullable:true)]
  public ?string $totalValue = null;

  #[ORM\Column(name:'currency', type:'string', length:3)]
  public string $currency;
}
