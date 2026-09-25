<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Attachment;

use DateTimeImmutable;
use Inspection\Domain\ValueObject\{InspectionAttachmentFile, InspectionAttachmentId, InspectionId, NonConformityId};

/**
 * Model InspectionAttachment.
 *
 * A file attachment linked to an inspection, or — when `nonConformityId` is
 * set — the field-proof photo of one non-conformity discovered during that
 * inspection. Both share a single `inspection_attachments` table with a
 * nullable `non_conformity_id` discriminator (see `src/Inspection/MODULE.md`).
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionAttachment
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InspectionAttachmentId $id the attachment identifier
   * @param InspectionId $inspectionId the inspection identifier
   * @param InspectionAttachmentFile $file the file metadata
   * @param DateTimeImmutable $uploadedAt the upload timestamp
   * @param ?NonConformityId $nonConformityId the optional non-conformity identifier (field-proof photo)
   */
  private function __construct(
    private InspectionAttachmentId $id,
    private InspectionId $inspectionId,
    private InspectionAttachmentFile $file,
    private DateTimeImmutable $uploadedAt,
    private ?NonConformityId $nonConformityId = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new inspection attachment.
   *
   * @since 1.0.0
   *
   * @param InspectionAttachmentId $id the attachment identifier
   * @param InspectionId $inspectionId the inspection identifier
   * @param InspectionAttachmentFile $file the file metadata
   * @param ?NonConformityId $nonConformityId the optional non-conformity identifier
   *
   * @return self the created attachment
   */
  public static function create(
    InspectionAttachmentId $id,
    InspectionId $inspectionId,
    InspectionAttachmentFile $file,
    ?NonConformityId $nonConformityId = null,
  ): self {
    return new self(
      id: $id,
      inspectionId: $inspectionId,
      file: $file,
      uploadedAt: new DateTimeImmutable(),
      nonConformityId: $nonConformityId,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes an attachment from persisted state.
   *
   * @since 1.0.0
   *
   * @param InspectionAttachmentId $id the attachment identifier
   * @param InspectionId $inspectionId the inspection identifier
   * @param InspectionAttachmentFile $file the persisted file metadata
   * @param DateTimeImmutable $uploadedAt the upload timestamp
   * @param ?NonConformityId $nonConformityId the optional non-conformity identifier
   *
   * @return self the reconstituted attachment
   */
  public static function reconstitute(
    InspectionAttachmentId $id,
    InspectionId $inspectionId,
    InspectionAttachmentFile $file,
    DateTimeImmutable $uploadedAt,
    ?NonConformityId $nonConformityId = null,
  ): self {
    return new self(
      id: $id,
      inspectionId: $inspectionId,
      file: $file,
      uploadedAt: $uploadedAt,
      nonConformityId: $nonConformityId,
    );
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): InspectionAttachmentId
  {
    return $this->id;
  }

  /**
   * Method inspectionId.
   *
   * @since 1.0.0
   */
  public function inspectionId(): InspectionId
  {
    return $this->inspectionId;
  }

  /**
   * Method nonConformityId.
   *
   * @since 1.0.0
   */
  public function nonConformityId(): ?NonConformityId
  {
    return $this->nonConformityId;
  }

  /**
   * Method fileName.
   *
   * @since 1.0.0
   */
  public function fileName(): string
  {
    return $this->file->fileName;
  }

  /**
   * Method storagePath.
   *
   * @since 1.0.0
   */
  public function storagePath(): string
  {
    return $this->file->storagePath;
  }

  /**
   * Method mimeType.
   *
   * @since 1.0.0
   */
  public function mimeType(): string
  {
    return $this->file->mimeType;
  }

  /**
   * Method size.
   *
   * @since 1.0.0
   */
  public function size(): int
  {
    return $this->file->size;
  }

  /**
   * Method label.
   *
   * @since 1.0.0
   */
  public function label(): ?string
  {
    return $this->file->label;
  }

  /**
   * Method uploadedAt.
   *
   * @since 1.0.0
   */
  public function uploadedAt(): DateTimeImmutable
  {
    return $this->uploadedAt;
  }
  // #endregion
}
