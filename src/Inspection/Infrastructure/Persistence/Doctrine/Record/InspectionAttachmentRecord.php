<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record InspectionAttachmentRecord.
 *
 * Backs both inspection-level attachments and non-conformity field-proof
 * photos through the nullable `non_conformity_id` discriminator (see
 * `src/Inspection/MODULE.md`).
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'inspection_attachments')]
#[ORM\Index(name: 'idx_inspection_attachment_inspection', columns: ['inspection_id'])]
#[ORM\Index(name: 'idx_inspection_attachment_non_conformity', columns: ['non_conformity_id'])]
#[ORM\UniqueConstraint(name: 'uniq_inspection_attachment_storage_path', columns: ['storage_path'])]
class InspectionAttachmentRecord
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
   * Property inspection.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: InspectionRecord::class)]
  #[ORM\JoinColumn(name: 'inspection_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?InspectionRecord $inspection = null;

  /**
   * Property nonConformity.
   *
   * Nullable discriminator: set when this attachment is a non-conformity
   * field-proof photo rather than an inspection-level document.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: NonConformityRecord::class)]
  #[ORM\JoinColumn(name: 'non_conformity_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
  public ?NonConformityRecord $nonConformity = null;

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
