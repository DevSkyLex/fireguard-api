<?php

declare(strict_types=1);

namespace MaintenanceExport\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class MaintenanceExportDocumentRecord
 *
 * Retains exact generated artifacts and their source baseline independently from import acknowledgement.
 *
 * @category Record
 */
#[ORM\Entity]
#[ORM\Table(name: 'maintenance_export_documents')]
#[ORM\UniqueConstraint(name: 'uniq_maintenance_export_adjustment', columns: ['organization_id', 'adjustment_of'])]
#[ORM\Index(name: 'idx_maintenance_export_directory', columns: ['organization_id', 'created_at', 'id'])]
class MaintenanceExportDocumentRecord
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
   * Property actorId
   */
  #[ORM\Column(name: 'actor_id', length: 36)]
  public string $actorId;

  /**
   * Property kind
   */
  #[ORM\Column(length: 32)]
  public string $kind;

  /**
   * Property system
   */
  #[ORM\Column(length: 64)]
  public string $system;

  /**
   * Property includeInternalCosts
   */
  #[ORM\Column(name: 'include_internal_costs', type: 'boolean')]
  public bool $includeInternalCosts;

  /**
   * Property sourceInterventionIds
   *
   * @var list<string> preserved source identities
   */
  #[ORM\Column(name: 'source_intervention_ids', type: 'json', options: ['jsonb' => true])]
  public array $sourceInterventionIds;

  /**
   * Property originalExportId
   */
  #[ORM\Column(name: 'original_export_id', length: 36, nullable: true)]
  public ?string $originalExportId = null;

  /**
   * Property adjustmentOf
   */
  #[ORM\Column(name: 'adjustment_of', length: 36, nullable: true)]
  public ?string $adjustmentOf = null;

  /**
   * Property reason
   */
  #[ORM\Column(type: 'text', nullable: true)]
  public ?string $reason = null;

  /**
   * Property createdAt
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property rows
   *
   * @var list<array<string,mixed>> immutable exported rows
   */
  #[ORM\Column(type: 'json', options: ['jsonb' => true])]
  public array $rows;

  /**
   * Property baseline
   *
   * @var array<string,array<string,mixed>> source state for later deltas
   */
  #[ORM\Column(type: 'json', options: ['jsonb' => true])]
  public array $baseline;

  /**
   * Property jsonBytes
   */
  #[ORM\Column(name: 'json_bytes', type: 'text')]
  public string $jsonBytes;

  /**
   * Property csvBytes
   */
  #[ORM\Column(name: 'csv_bytes', type: 'text')]
  public string $csvBytes;

  /**
   * Property jsonSha256
   */
  #[ORM\Column(name: 'json_sha256', length: 64)]
  public string $jsonSha256;

  /**
   * Property csvSha256
   */
  #[ORM\Column(name: 'csv_sha256', length: 64)]
  public string $csvSha256;

  /**
   * Property immutableHash
   */
  #[ORM\Column(name: 'immutable_hash', length: 64)]
  public string $immutableHash;

  /**
   * Property costsComplete
   */
  #[ORM\Column(name: 'costs_complete', type: 'boolean', nullable: true)]
  public ?bool $costsComplete = null;

  /**
   * Property incompleteCostCount
   */
  #[ORM\Column(name: 'incomplete_cost_count', type: 'integer', nullable: true)]
  public ?int $incompleteCostCount = null;

  /**
   * Property revision
   */
  #[ORM\Column(type: 'integer')]
  public int $revision;

  /**
   * Property confirmation
   *
   * @var array<string,mixed>|null explicit import acknowledgement
   */
  #[ORM\Column(type: 'json', nullable: true, options: ['jsonb' => true])]
  public ?array $confirmation = null;
  // #endregion
}
