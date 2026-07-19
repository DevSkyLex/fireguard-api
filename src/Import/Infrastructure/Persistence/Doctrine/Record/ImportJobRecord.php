<?php

declare(strict_types=1);

namespace Import\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record ImportJobRecord.
 *
 * `organization_id` is a plain column (not an ORM association) with its
 * foreign key added directly in the migration, mirroring
 * `intervention_attachments.work_item_id`'s precedent
 * (`Version20260717111309`) — the FK exists at the database level without
 * requiring an inverse collection on `OrganizationRecord`.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'import_jobs')]
#[ORM\Index(name: 'idx_import_job_org_status', columns: ['organization_id', 'status'])]
#[ORM\Index(name: 'idx_import_job_created', columns: ['created_at'])]
class ImportJobRecord
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
   * Property kind.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'kind', type: 'string', length: 16)]
  public string $kind;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status = 'pending';

  /**
   * Property storagePath.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'storage_path', type: 'string', length: 512)]
  public string $storagePath;

  /**
   * Property originalFilename.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'original_filename', type: 'string', length: 255)]
  public string $originalFilename;

  /**
   * Property totalRows.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'total_rows', type: 'integer', nullable: true)]
  public ?int $totalRows = null;

  /**
   * Property processedRows.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'processed_rows', type: 'integer')]
  public int $processedRows = 0;

  /**
   * Property successfulRows.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'successful_rows', type: 'integer')]
  public int $successfulRows = 0;

  /**
   * Property failedRows.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'failed_rows', type: 'integer')]
  public int $failedRows = 0;

  /**
   * Property errorReport.
   *
   * List of `{rowNumber, column, code, message}` maps. Counts and reasons
   * only — row payload values are never stored here (PII hygiene).
   *
   * @since 1.0.0
   *
   * @var list<array{rowNumber: int, column: ?string, code: string, message: string}>|null
   */
  #[ORM\Column(name: 'error_report', type: 'json', nullable: true)]
  public ?array $errorReport = null;

  /**
   * Property jobError.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'job_error', type: 'text', nullable: true)]
  public ?string $jobError = null;

  /**
   * Property createdBy.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_by', type: 'string', length: 36)]
  public string $createdBy;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;

  /**
   * Property startedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'started_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $startedAt = null;

  /**
   * Property completedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $completedAt = null;
  // #endregion
}
