<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Attachment;

use DateTimeImmutable;
use Inspection\Domain\ValueObject\{InspectionAttachmentId, InspectionId, NonConformityId};

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
   * @param string $fileName the original file name
   * @param string $storagePath the storage path
   * @param string $mimeType the MIME type
   * @param int $size the file size in bytes
   * @param DateTimeImmutable $uploadedAt the upload timestamp
   * @param ?NonConformityId $nonConformityId the optional non-conformity identifier (field-proof photo)
   * @param ?string $label the optional label
   */
  private function __construct(
    private InspectionAttachmentId $id,
    private InspectionId $inspectionId,
    private string $fileName,
    private string $storagePath,
    private string $mimeType,
    private int $size,
    private DateTimeImmutable $uploadedAt,
    private ?NonConformityId $nonConformityId = null,
    private ?string $label = null,
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
   * @param string $fileName the original file name
   * @param string $storagePath the storage path
   * @param string $mimeType the MIME type
   * @param int $size the file size in bytes
   * @param ?NonConformityId $nonConformityId the optional non-conformity identifier
   * @param ?string $label the optional label
   *
   * @return self the created attachment
   */
  public static function create(
    InspectionAttachmentId $id,
    InspectionId $inspectionId,
    string $fileName,
    string $storagePath,
    string $mimeType,
    int $size,
    ?NonConformityId $nonConformityId = null,
    ?string $label = null,
  ): self {
    return new self(
      id: $id,
      inspectionId: $inspectionId,
      fileName: $fileName,
      storagePath: $storagePath,
      mimeType: $mimeType,
      size: $size,
      uploadedAt: new DateTimeImmutable(),
      nonConformityId: $nonConformityId,
      label: $label,
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
   * @param string $fileName the original file name
   * @param string $storagePath the storage path
   * @param string $mimeType the MIME type
   * @param int $size the file size in bytes
   * @param DateTimeImmutable $uploadedAt the upload timestamp
   * @param ?NonConformityId $nonConformityId the optional non-conformity identifier
   * @param ?string $label the optional label
   *
   * @return self the reconstituted attachment
   */
  public static function reconstitute(
    InspectionAttachmentId $id,
    InspectionId $inspectionId,
    string $fileName,
    string $storagePath,
    string $mimeType,
    int $size,
    DateTimeImmutable $uploadedAt,
    ?NonConformityId $nonConformityId = null,
    ?string $label = null,
  ): self {
    return new self(
      id: $id,
      inspectionId: $inspectionId,
      fileName: $fileName,
      storagePath: $storagePath,
      mimeType: $mimeType,
      size: $size,
      uploadedAt: $uploadedAt,
      nonConformityId: $nonConformityId,
      label: $label,
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
    return $this->fileName;
  }

  /**
   * Method storagePath.
   *
   * @since 1.0.0
   */
  public function storagePath(): string
  {
    return $this->storagePath;
  }

  /**
   * Method mimeType.
   *
   * @since 1.0.0
   */
  public function mimeType(): string
  {
    return $this->mimeType;
  }

  /**
   * Method size.
   *
   * @since 1.0.0
   */
  public function size(): int
  {
    return $this->size;
  }

  /**
   * Method label.
   *
   * @since 1.0.0
   */
  public function label(): ?string
  {
    return $this->label;
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
