<?php

declare(strict_types=1);

namespace Procurement\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Main-database record retaining procurement evidence without historical deletion. */
#[ORM\Entity]
#[ORM\Table(name: 'procurement_orders')]
#[ORM\Index(name: 'idx_procurement_order_org_supplier_status', columns: ['organization_id', 'supplier_id', 'status'])]
class PurchaseOrderRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'id', type: 'string', length: 36)]
  public string $id;

  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'supplier_id', type: 'string', length: 36)]
  public string $supplierId;

  #[ORM\Column(name: 'name', type: 'string', length: 160)]
  public string $name;

  #[ORM\Column(name: 'currency', type: 'string', length: 3)]
  public string $currency;

  #[ORM\Column(name: 'status', type: 'string', length: 32)]
  public string $status;

  /**
   * @var array<array-key,mixed>
   */
  #[ORM\Column(name: 'lines', type: 'json', options: ['jsonb' => true])]
  public array $lines;

  #[ORM\Column(name: 'revision', type: 'integer')]
  public int $revision;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
}
