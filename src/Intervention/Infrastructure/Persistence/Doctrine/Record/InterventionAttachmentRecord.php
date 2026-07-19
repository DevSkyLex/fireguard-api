<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record InterventionAttachmentRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'intervention_attachments')]
#[ORM\Index(name: 'idx_intervention_attachment_intervention', columns: ['intervention_id'])]
#[ORM\UniqueConstraint(name: 'uniq_intervention_attachment_storage_path', columns: ['storage_path'])]
class InterventionAttachmentRecord
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
   * Property intervention.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: InterventionRecord::class)]
  #[ORM\JoinColumn(name: 'intervention_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?InterventionRecord $intervention = null;

  /**
   * Property workItemId.
   *
   * Reserved for a future optional per-work-item attach scope (not exposed
   * by any endpoint of this lot — see `src/Intervention/MODULE.md`). Stored
   * as a plain column (not an ORM association) so the FK is added directly
   * in the migration, mirroring `InterventionRecurrenceRecord::$rrule`.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'work_item_id', type: 'string', length: 36, nullable: true)]
  public ?string $workItemId = null;

  /**
   * Property fileName.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'file_name', type: 'string', length: 255)]
  public string $fileName;

  /**
   * Property storagePath.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'storage_path', type: 'string', length: 500)]
  public string $storagePath;

  /**
   * Property mimeType.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'mime_type', type: 'string', length: 100)]
  public string $mimeType;

  /**
   * Property size.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'size', type: 'bigint')]
  public int $size;

  /**
   * Property label.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'label', type: 'string', length: 255, nullable: true)]
  public ?string $label = null;

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'integer')]
  public int $revision = 1;

  /**
   * Property uploadedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'uploaded_at', type: 'datetime_immutable')]
  public DateTimeImmutable $uploadedAt;
  // #endregion
}
