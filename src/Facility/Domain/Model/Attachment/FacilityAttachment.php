<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Attachment;

use DateTimeImmutable;
use Facility\Domain\Exception\FacilityAttachmentNotFloorPlanException;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, PlanCalibration};
use Shared\Domain\Attachment\InvalidAttachmentException;

use function in_array;

/**
 * Model FacilityAttachment.
 *
 * @category Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityAttachment
{
  // #region Properties
  /**
   * Property file
   *
   * Immutable metadata of the stored file, shared by each projection without another allocation.
   */
  private readonly FacilityAttachmentFile $file;
  // #endregion

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
    string $fileName,
    string $storagePath,
    string $mimeType,
    int $size,
    DateTimeImmutable $uploadedAt,
    private ?string $label = null,
    private AttachmentKind $kind = AttachmentKind::DOCUMENT,
    private bool $isPrimaryPlan = false,
    private ?int $imageWidth = null,
    private ?int $imageHeight = null,
    private ?PlanCalibration $calibration = null,
    private int $revision = 1,
    private ?string $calibrationBuildingId = null,
  ) {
    $this->file = new FacilityAttachmentFile($fileName, $storagePath, $mimeType, $size, $uploadedAt);
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
   * @param ?FacilityAttachmentCreationOptions $options the optional attachment details
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
    ?FacilityAttachmentCreationOptions $options = null,
  ): self {
    $options ??= new FacilityAttachmentCreationOptions();

    return new self(
      id: $id,
      facilityId: $facilityId,
      fileName: $fileName,
      storagePath: $storagePath,
      mimeType: $mimeType,
      size: $size,
      uploadedAt: new DateTimeImmutable(),
      label: $options->label,
      kind: $options->kind,
      isPrimaryPlan: false,
      imageWidth: $options->imageWidth,
      imageHeight: $options->imageHeight,
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
   * @param FacilityAttachmentRestoredState $state the persisted attachment details
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
    FacilityAttachmentRestoredState $state,
  ): self {
    $options = $state->options ?? new FacilityAttachmentCreationOptions();

    return new self(
      id: $id,
      facilityId: $facilityId,
      fileName: $fileName,
      storagePath: $storagePath,
      mimeType: $mimeType,
      size: $size,
      uploadedAt: $state->uploadedAt,
      label: $options->label,
      kind: $options->kind,
      isPrimaryPlan: $state->isPrimaryPlan,
      calibration: $state->calibration,
      revision: $state->revision,
      calibrationBuildingId: $state->calibrationBuildingId,
      imageWidth: $options->imageWidth,
      imageHeight: $options->imageHeight,
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
   * Method file
   *
   * Returns the immutable metadata of the original stored file.
   *
   * @access public
   * @since 1.0.0
   *
   * @return FacilityAttachmentFile persisted file metadata
   */
  public function file(): FacilityAttachmentFile
  {
    return $this->file;
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
   * Method revision.
   *
   * @since 1.0.0
   */
  public function revision(): int
  {
    return $this->revision;
  }

  /**
   * Method calibration.
   *
   * @since 1.0.0
   */
  public function calibration(): ?PlanCalibration
  {
    return $this->calibration;
  }

  /**
   * Method calibrationBuildingId
   *
   * Returns the building coordinate frame in which the retained calibration was confirmed.
   *
   * @access public
   *
   * @return ?string confirmed building identifier, null when its frame is unverified
   */
  public function calibrationBuildingId(): ?string
  {
    return $this->calibrationBuildingId;
  }

  /**
   * Method calibrate.
   *
   * Confirms a calibration in its server-resolved building frame; clearing it also clears provenance.
   *
   * @since 1.0.0
   *
   * @param ?PlanCalibration $calibration the retained image calibration, null clearing it
   * @param ?string $buildingId the owning facility's current nearest building, resolved by the server
   */
  public function calibrate(?PlanCalibration $calibration, ?string $buildingId = null): void
  {
    if (AttachmentKind::FLOOR_PLAN !== $this->kind) {
      throw FacilityAttachmentNotFloorPlanException::forAttachment((string) $this->id);
    }
    if (null !== $calibration && (null === $this->imageWidth || null === $this->imageHeight || $this->imageWidth <= 0 || $this->imageHeight <= 0)) {
      throw \Shared\Domain\Exception\InvalidValueException::because('A calibrated plan must have known image dimensions.');
    }
    $this->calibration = $calibration;
    $this->calibrationBuildingId = null === $calibration ? null : $buildingId;
    ++$this->revision;
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
    if (null !== $allowedMimeTypes && !in_array($this->file->mimeType, $allowedMimeTypes, true)) {
      throw InvalidAttachmentException::forMimeType($this->file->mimeType);
    }

    if ($this->isPrimaryPlan && AttachmentKind::FLOOR_PLAN !== $this->kind) {
      throw FacilityAttachmentNotFloorPlanException::forAttachment((string) $this->id);
    }
  }
  // #endregion
}
