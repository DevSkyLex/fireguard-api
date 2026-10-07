<?php

declare(strict_types=1);

namespace MaintenanceExport\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class MaintenanceExternalReferenceRecord
 *
 * Maps a scoped resource to one explicit ERP system without rewriting saved artifacts.
 *
 * @category Record
 */
#[ORM\Entity]
#[ORM\Table(name: 'maintenance_external_references')]
#[ORM\UniqueConstraint(name: 'uniq_maintenance_external_reference', columns: ['organization_id', 'system', 'resource_type', 'resource_id'])]
class MaintenanceExternalReferenceRecord
{
  // #region Properties
  /**
   * Property id
   */
  #[ORM\Id]
  #[ORM\Column(length: 36)]
  public string $id;

  /**
   * Property organizationId
   */
  #[ORM\Column(name: 'organization_id', length: 36)]
  public string $organizationId;

  /**
   * Property system
   */
  #[ORM\Column(length: 64)]
  public string $system;

  /**
   * Property resourceType
   */
  #[ORM\Column(name: 'resource_type', length: 32)]
  public string $resourceType;

  /**
   * Property resourceId
   */
  #[ORM\Column(name: 'resource_id', length: 36)]
  public string $resourceId;

  /**
   * Property reference
   */
  #[ORM\Column(length: 200)]
  public string $reference;

  /**
   * Property revision
   */
  #[ORM\Column(type: 'integer')]
  public int $revision;

  /**
   * Property updatedAt
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
  // #endregion
}
