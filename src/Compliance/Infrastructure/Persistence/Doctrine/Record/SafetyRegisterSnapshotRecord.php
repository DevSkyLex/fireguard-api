<?php

declare(strict_types=1);

namespace Compliance\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record SafetyRegisterSnapshotRecord.
 *
 * Archived safety register snapshot metadata (main database). The PDF bytes
 * live in file storage under `storage_path`, never in this table.
 * `organization_id` and `facility_id` are plain columns (not ORM
 * associations), mirroring `import_jobs.organization_id`'s precedent — the
 * cross-module link stays an identifier, without an inverse collection on
 * the owning records. Rows are append-only: a snapshot is never updated.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'compliance_register_snapshots')]
#[ORM\Index(name: 'idx_register_snapshot_org_generated', columns: ['organization_id', 'generated_at'])]
class SafetyRegisterSnapshotRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property facilityId.
   *
   * Null for an organization-wide register snapshot.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'facility_id', type: 'string', length: 36, nullable: true)]
  public ?string $facilityId = null;

  /**
   * Property generatedAt.
   *
   * ISO 8601 datetime string, exactly as carried by the register read-model
   * and printed on the archived document.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'generated_at', type: 'string', length: 64)]
  public string $generatedAt;

  /**
   * Property generatedByUserId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'generated_by_user_id', type: 'string', length: 36)]
  public string $generatedByUserId;

  /**
   * Property contentHash.
   *
   * SHA-256 hex digest of the stored PDF bytes.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'content_hash', type: 'string', length: 64)]
  public string $contentHash;

  /**
   * Property sizeBytes.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'size_bytes', type: 'integer')]
  public int $sizeBytes;

  /**
   * Property storagePath.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'storage_path', type: 'string', length: 512)]
  public string $storagePath;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;
  // #endregion
}
