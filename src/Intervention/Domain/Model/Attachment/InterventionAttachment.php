<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Attachment;

use DateTimeImmutable;
use Intervention\Domain\ValueObject\{InterventionAttachmentId, InterventionAttachmentKind};

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
   * @param ?string $workItemId the optional owning work item identifier
   * @param InterventionAttachmentKind $kind the attachment kind (plain file or the typed completion signature)
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
    private ?string $workItemId = null,
    private InterventionAttachmentKind $kind = InterventionAttachmentKind::FILE,
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
   * @param ?string $workItemId the optional owning work item identifier
   * @param InterventionAttachmentKind $kind the attachment kind
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
    ?string $workItemId = null,
    InterventionAttachmentKind $kind = InterventionAttachmentKind::FILE,
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
      workItemId: $workItemId,
      kind: $kind,
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
   * @param ?string $workItemId the optional owning work item identifier
   * @param InterventionAttachmentKind $kind the attachment kind
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
    ?string $workItemId = null,
    InterventionAttachmentKind $kind = InterventionAttachmentKind::FILE,
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
      workItemId: $workItemId,
      kind: $kind,
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

  /**
   * Method workItemId.
   *
   * The optional intervention work item this attachment is scoped to. Null
   * when the attachment is a plain intervention-level attachment.
   *
   * @since 1.1.0
   */
  public function workItemId(): ?string
  {
    return $this->workItemId;
  }

  /**
   * Method kind.
   *
   * The attachment kind — a plain evidence file, or the typed completion
   * signature captured at submission time.
   *
   * @since 1.2.0
   */
  public function kind(): InterventionAttachmentKind
  {
    return $this->kind;
  }
  // #endregion
}
