<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\Attachment;

use DateTimeImmutable;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId};

/**
 * Model EquipmentAttachment.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentAttachment
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AttachmentId $id the attachment identifier
   * @param EquipmentId $equipmentId the equipment identifier
   * @param string $fileName the original file name
   * @param string $storagePath the storage path
   * @param string $mimeType the MIME type
   * @param int $size the file size in bytes
   * @param DateTimeImmutable $uploadedAt the upload timestamp
   * @param ?string $label the optional label
   */
  private function __construct(
    private AttachmentId $id,
    private EquipmentId $equipmentId,
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
   * Creates a new equipment attachment.
   *
   * @since 1.0.0
   *
   * @param AttachmentId $id the attachment identifier
   * @param EquipmentId $equipmentId the equipment identifier
   * @param string $fileName the original file name
   * @param string $storagePath the storage path
   * @param string $mimeType the MIME type
   * @param int $size the file size in bytes
   * @param ?string $label the optional label
   *
   * @return self the created attachment
   */
  public static function create(
    AttachmentId $id,
    EquipmentId $equipmentId,
    string $fileName,
    string $storagePath,
    string $mimeType,
    int $size,
    ?string $label = null,
  ): self {
    return new self(
      id: $id,
      equipmentId: $equipmentId,
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
   * @param AttachmentId $id the attachment identifier
   * @param EquipmentId $equipmentId the equipment identifier
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
    AttachmentId $id,
    EquipmentId $equipmentId,
    string $fileName,
    string $storagePath,
    string $mimeType,
    int $size,
    DateTimeImmutable $uploadedAt,
    ?string $label = null,
  ): self {
    return new self(
      id: $id,
      equipmentId: $equipmentId,
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
  public function id(): AttachmentId
  {
    return $this->id;
  }

  /**
   * Method equipmentId.
   *
   * @since 1.0.0
   */
  public function equipmentId(): EquipmentId
  {
    return $this->equipmentId;
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
