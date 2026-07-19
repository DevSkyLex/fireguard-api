<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Attachment;

use DateTimeImmutable;
use Intervention\Domain\ValueObject\InterventionAttachmentId;

/**
 * Model InterventionAttachment.
 *
 * A file attachment linked directly to an intervention (e.g. execution
 * evidence photos). `interventionId` is a plain string, mirroring
 * `Intervention\Domain\Model\Intervention\Intervention`, which does not use
 * a dedicated identifier value object.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionAttachment
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionAttachmentId $id the attachment identifier
   * @param string $interventionId the intervention identifier
   * @param string $fileName the original file name
   * @param string $storagePath the storage path
   * @param string $mimeType the MIME type
   * @param int $size the file size in bytes
   * @param DateTimeImmutable $uploadedAt the upload timestamp
   * @param ?string $label the optional label
   */
  private function __construct(
    private InterventionAttachmentId $id,
    private string $interventionId,
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
   * Creates a new intervention attachment.
   *
   * @since 1.0.0
   *
   * @param InterventionAttachmentId $id the attachment identifier
   * @param string $interventionId the intervention identifier
   * @param string $fileName the original file name
   * @param string $storagePath the storage path
   * @param string $mimeType the MIME type
   * @param int $size the file size in bytes
   * @param ?string $label the optional label
   *
   * @return self the created attachment
   */
  public static function create(
    InterventionAttachmentId $id,
    string $interventionId,
    string $fileName,
    string $storagePath,
    string $mimeType,
    int $size,
    ?string $label = null,
  ): self {
    return new self(
      id: $id,
      interventionId: $interventionId,
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
   * @param InterventionAttachmentId $id the attachment identifier
   * @param string $interventionId the intervention identifier
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
    InterventionAttachmentId $id,
    string $interventionId,
    string $fileName,
    string $storagePath,
    string $mimeType,
    int $size,
    DateTimeImmutable $uploadedAt,
    ?string $label = null,
  ): self {
    return new self(
      id: $id,
      interventionId: $interventionId,
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
  public function id(): InterventionAttachmentId
  {
    return $this->id;
  }

  /**
   * Method interventionId.
   *
   * @since 1.0.0
   */
  public function interventionId(): string
  {
    return $this->interventionId;
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
