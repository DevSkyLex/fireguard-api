<?php

declare(strict_types=1);

namespace Procurement\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Main-database record retaining procurement evidence without historical deletion. */
#[ORM\Entity]
#[ORM\Table(name: 'procurement_suppliers')]
#[ORM\Index(name: 'idx_procurement_supplier_org_archive', columns: ['organization_id', 'archived_at'])]
class SupplierRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'id', type: 'string', length: 36)]
  public string $id;

  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'name', type: 'string', length: 160)]
  public string $name;

  #[ORM\Column(name: 'code', type: 'string', length: 80, nullable: true)]
  public ?string $code = null;

  #[ORM\Column(name: 'email', type: 'string', length: 254, nullable: true)]
  public ?string $email = null;

  #[ORM\Column(name: 'phone', type: 'string', length: 40, nullable: true)]
  public ?string $phone = null;

  /**
   * @var array<array-key,mixed>
   */
  #[ORM\Column(name: 'contacts', type: 'json', options: ['jsonb' => true])]
  public array $contacts;

  #[ORM\Column(name: 'archived_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $archivedAt = null;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;

  #[ORM\Column(name: 'revision', type: 'integer')]
  public int $revision;
}
