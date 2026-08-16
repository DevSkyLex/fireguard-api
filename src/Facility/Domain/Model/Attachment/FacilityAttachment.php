<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Attachment;

use DateTimeImmutable;
use Facility\Domain\Exception\FacilityAttachmentNotFloorPlanException;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId};
use Shared\Domain\Attachment\InvalidAttachmentException;

use function in_array;

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
   * @param AttachmentKind $kind the attachment kind (document or floor plan)
   * @param bool $isPrimaryPlan whether this is the facility's primary floor plan
   * @param ?int $imageWidth the probed image width in pixels, floor plans only
   * @param ?int $imageHeight the probed image height in pixels, floor plans only
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
    private AttachmentKind $kind = AttachmentKind::DOCUMENT,
    private bool $isPrimaryPlan = false,
    private ?int $imageWidth = null,
    private ?int $imageHeight = null,
  ) {
    $this->assertInvariants();
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
   * @param AttachmentKind $kind the attachment kind
   * @param ?int $imageWidth the probed image width in pixels, floor plans only
   * @param ?int $imageHeight the probed image height in pixels, floor plans only
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
    AttachmentKind $kind = AttachmentKind::DOCUMENT,
    ?int $imageWidth = null,
    ?int $imageHeight = null,
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
      kind: $kind,
      isPrimaryPlan: false,
      imageWidth: $imageWidth,
      imageHeight: $imageHeight,
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
   * @param AttachmentKind $kind the attachment kind
   * @param bool $isPrimaryPlan whether this is the facility's primary floor plan
   * @param ?int $imageWidth the probed image width in pixels, floor plans only
   * @param ?int $imageHeight the probed image height in pixels, floor plans only
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
    AttachmentKind $kind = AttachmentKind::DOCUMENT,
    bool $isPrimaryPlan = false,
    ?int $imageWidth = null,
    ?int $imageHeight = null,
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
      kind: $kind,
      isPrimaryPlan: $isPrimaryPlan,
      imageWidth: $imageWidth,
      imageHeight: $imageHeight,
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

  /**
   * Method kind.
   *
   * @since 1.1.0
   */
  public function kind(): AttachmentKind
  {
    return $this->kind;
  }

  /**
   * Method isPrimaryPlan.
   *
   * @since 1.1.0
   */
  public function isPrimaryPlan(): bool
  {
    return $this->isPrimaryPlan;
  }

  /**
   * Method imageWidth.
   *
   * @since 1.1.0
   */
  public function imageWidth(): ?int
  {
    return $this->imageWidth;
  }

  /**
   * Method imageHeight.
   *
   * @since 1.1.0
   */
  public function imageHeight(): ?int
  {
    return $this->imageHeight;
  }

  /**
   * Method markAsPrimary.
   *
   * Promotes this attachment to the facility's primary floor plan. Only a
   * `FLOOR_PLAN` attachment may become primary — clearing the previous
   * primary of the same facility, if any, is the caller's (repository's)
   * responsibility, kept atomic with the persist of this flag.
   *
   * @since 1.1.0
   *
   * @throws FacilityAttachmentNotFloorPlanException when this attachment is not a floor plan
   */
  public function markAsPrimary(): void
  {
    if (AttachmentKind::FLOOR_PLAN !== $this->kind) {
      throw FacilityAttachmentNotFloorPlanException::forAttachment((string) $this->id);
    }

    $this->isPrimaryPlan = true;
  }

  /**
   * Method clearPrimary.
   *
   * Demotes this attachment from being the facility's primary floor plan.
   *
   * @since 1.1.0
   */
  public function clearPrimary(): void
  {
    $this->isPrimaryPlan = false;
  }

  /**
   * Method assertInvariants.
   *
   * A `FLOOR_PLAN` attachment must carry an image MIME type; a `DOCUMENT`
   * attachment can never be the primary plan.
   *
   * @since 1.1.0
   *
   * @throws InvalidAttachmentException when a floor plan carries a non-image MIME type
   * @throws FacilityAttachmentNotFloorPlanException when a document is marked primary
   */
  private function assertInvariants(): void
  {
    $allowedMimeTypes = $this->kind->allowedMimeTypes();
    if (null !== $allowedMimeTypes && !in_array($this->mimeType, $allowedMimeTypes, true)) {
      throw InvalidAttachmentException::forMimeType($this->mimeType);
    }

    if ($this->isPrimaryPlan && AttachmentKind::FLOOR_PLAN !== $this->kind) {
      throw FacilityAttachmentNotFloorPlanException::forAttachment((string) $this->id);
    }
  }
  // #endregion
}
