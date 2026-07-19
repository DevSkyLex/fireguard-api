<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Attachment;

use DateTimeImmutable;
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId};

/**
 * Model FacilityAttachment.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityAttachment
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param FacilityAttachmentId $id the attachment identifier
   * @param FacilityId $facilityId the facility identifier
   * @param string $fileName the original file name
   * @param string $storagePath the storage path
   * @param string $mimeType the MIME type
   * @param int $size the file size in bytes
   * @param DateTimeImmutable $uploadedAt the upload timestamp
   * @param ?string $label the optional label
   */
  private function __construct(
    private FacilityAttachmentId $id,
    private FacilityId $facilityId,
    private string $fileName,
    private string $storagePath,
    private string $mimeType,
    private int $size,
    private DateTimeImmutable $uploadedAt,
    private ?string $label = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new facility attachment.
   *
   * @since 1.0.0
   *
   * @param FacilityAttachmentId $id the attachment identifier
   * @param FacilityId $facilityId the facility identifier
   * @param string $fileName the original file name
   * @param string $storagePath the storage path
   * @param string $mimeType the MIME type
   * @param int $size the file size in bytes
   * @param ?string $label the optional label
   *
   * @return self the created attachment
   */
  public static function create(
    FacilityAttachmentId $id,
    FacilityId $facilityId,
    string $fileName,
    string $storagePath,
    string $mimeType,
    int $size,
    ?string $label = null,
  ): self {
    return new self(
      id: $id,
      facilityId: $facilityId,
      fileName: $fileName,
      storagePath: $storagePath,
      mimeType: $mimeType,
      size: $size,
      uploadedAt: new DateTimeImmutable(),
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
   * @param FacilityAttachmentId $id the attachment identifier
   * @param FacilityId $facilityId the facility identifier
   * @param string $fileName the original file name
   * @param string $storagePath the storage path
   * @param string $mimeType the MIME type
   * @param int $size the file size in bytes
   * @param DateTimeImmutable $uploadedAt the upload timestamp
   * @param ?string $label the optional label
   *
   * @return self the reconstituted attachment
   */
  public static function reconstitute(
    FacilityAttachmentId $id,
    FacilityId $facilityId,
    string $fileName,
    string $storagePath,
    string $mimeType,
    int $size,
    DateTimeImmutable $uploadedAt,
    ?string $label = null,
  ): self {
    return new self(
      id: $id,
      facilityId: $facilityId,
      fileName: $fileName,
      storagePath: $storagePath,
      mimeType: $mimeType,
      size: $size,
      uploadedAt: $uploadedAt,
      label: $label,
    );
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): FacilityAttachmentId
  {
    return $this->id;
  }

  /**
   * Method facilityId.
   *
   * @since 1.0.0
   */
  public function facilityId(): FacilityId
  {
    return $this->facilityId;
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
