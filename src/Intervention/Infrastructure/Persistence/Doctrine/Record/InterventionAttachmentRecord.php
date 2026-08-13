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
#[ORM\Index(name: 'idx_intervention_attachment_work_item', columns: ['work_item_id'])]
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
   * Property workItem.
   *
   * Optional per-work-item attach scope (Phase 5d.1) — mirrors
   * `InterventionChangeRecord::$workItem`. `SET NULL` on delete: deleting a
   * work item leaves its attachments in place as plain intervention-level
   * evidence rather than cascading their removal.
   *
   * @since 1.1.0
   */
  #[ORM\ManyToOne(targetEntity: InterventionWorkItemRecord::class)]
  #[ORM\JoinColumn(name: 'work_item_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
  public ?InterventionWorkItemRecord $workItem = null;

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
