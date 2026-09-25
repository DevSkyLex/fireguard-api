<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Inspection;

use DateTimeImmutable;
use Inspection\Domain\Exception\{InspectionAlreadyCancelledException, InspectionAlreadyClosedException, InspectionAlreadySubmittedException, InspectionNotSubmittedException};
use Inspection\Domain\ValueObject\{
  InspectionChecklistId,
  InspectionEquipmentId,
  InspectionFacilityId,
  InspectionFindingPatch,
  InspectionId,
  InspectionOrganizationId,
  InspectionReferencePatch,
  InspectionResult,
  InspectionStatus,
  Inspector
};
use Shared\Domain\Exception\InvalidValueException;

use function mb_strlen;
use function sprintf;
use function trim;

/**
 * Model Inspection.
 *
 * Aggregate root representing a physical inspection performed
 * on an equipment item. Records the outcome (pass/fail/partial)
 * and tracks associated non-conformities.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Inspection
{
  private InspectionEquipmentId $equipmentId;

  private Inspector $inspector;

  private InspectionResult $result;

  private InspectionStatus $status;

  private DateTimeImmutable $performedAt;

  private ?InspectionFacilityId $facilityId;

  private ?InspectionChecklistId $checklistId;

  private ?string $notes;

  private ?string $signature;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InspectionId $id the inspection identifier
   * @param InspectionOrganizationId $organizationId the organization identifier
   * @param InspectionReferences $references the equipment and inspector references
   * @param InspectionFinding $finding the finding and lifecycle state
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   */
  private function __construct(
    private InspectionId $id,
    private InspectionOrganizationId $organizationId,
    InspectionReferences $references,
    InspectionFinding $finding,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
  ) {
    $this->equipmentId = $references->equipmentId;
    $this->inspector = $references->inspector;
    $this->facilityId = $references->facilityId;
    $this->checklistId = $references->checklistId;
    $this->result = $finding->result;
    $this->status = $finding->status;
    $this->performedAt = $finding->performedAt;
    $this->notes = $finding->notes;
    $this->signature = $finding->signature;
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new inspection aggregate in DRAFT status.
   *
   * @since 1.0.0
   *
   * @param InspectionId $id the inspection identifier
   * @param InspectionOrganizationId $organizationId the organization identifier
   * @param InspectionEquipmentId $equipmentId the equipment identifier
   * @param Inspector $inspector the person who performed the inspection
   * @param InspectionResult $result the inspection result
   * @param DateTimeImmutable $performedAt when the inspection was performed
   * @param ?InspectionCreationOptions $options optional references and text
   *
   * @return self the created inspection aggregate
   */
  public static function create(
    InspectionId $id,
    InspectionOrganizationId $organizationId,
    InspectionEquipmentId $equipmentId,
    Inspector $inspector,
    InspectionResult $result,
    DateTimeImmutable $performedAt,
    ?InspectionCreationOptions $options = null,
  ): self {
    $now = new DateTimeImmutable();
    $options ??= new InspectionCreationOptions();

    return new self(
      id: $id,
      organizationId: $organizationId,
      references: new InspectionReferences($equipmentId, $inspector, $options->facilityId, $options->checklistId),
      finding: new InspectionFinding(
        $result,
        InspectionStatus::DRAFT,
        $performedAt,
        self::normalizeText($options->notes, 'notes', 5000),
        $options->signature,
      ),
      createdAt: $now,
      updatedAt: $now,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes an inspection aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param InspectionId $id the inspection identifier
   * @param InspectionOrganizationId $organizationId the organization identifier
   * @param InspectionReferences $references the persisted equipment and inspector references
   * @param InspectionFinding $finding the persisted finding and lifecycle
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   *
   * @return self the reconstituted inspection aggregate
   */
  public static function reconstitute(
    InspectionId $id,
    InspectionOrganizationId $organizationId,
    InspectionReferences $references,
    InspectionFinding $finding,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      references: $references,
      finding: $finding,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );
  }

  /**
   * Method submit.
   *
   * Submits the inspection for review.
   *
   * @since 1.0.0
   */
  public function submit(): void
  {
    if ($this->status->isClosed()) {
      throw InspectionAlreadyClosedException::withId((string) $this->id);
    }

    if (!$this->status->isDraft()) {
      throw InspectionAlreadySubmittedException::withId((string) $this->id);
    }

    $this->status = InspectionStatus::SUBMITTED;
    $this->touch();
  }

  /**
   * Method close.
   *
   * Closes the inspection.
   *
   * @since 1.0.0
   */
  public function close(): void
  {
    if ($this->status->isClosed()) {
      throw InspectionAlreadyClosedException::withId((string) $this->id);
    }

    if (!$this->status->isSubmitted()) {
      throw InspectionNotSubmittedException::withId((string) $this->id);
    }

    $this->status = InspectionStatus::CLOSED;
    $this->touch();
  }

  /**
   * Method cancel.
   *
   * Cancels the inspection logically (draft or submitted), preserving the row
   * and its non-conformities. A closed or already-cancelled inspection cannot
   * be cancelled.
   *
   * @since 1.0.0
   */
  public function cancel(): void
  {
    if ($this->status->isClosed()) {
      throw InspectionAlreadyClosedException::withId((string) $this->id);
    }

    if ($this->status->isCancelled()) {
      throw InspectionAlreadyCancelledException::withId((string) $this->id);
    }

    $this->status = InspectionStatus::CANCELLED;
    $this->touch();
  }

  /**
   * Method edit.
   *
   * Updates a draft inspection.
   *
   * @since 1.0.0
   */
  public function edit(
    ?InspectionReferencePatch $references = null,
    ?InspectionFindingPatch $finding = null,
  ): void {
    $references ??= new InspectionReferencePatch();
    $finding ??= new InspectionFindingPatch();

    if ($this->status->isClosed()) {
      throw InspectionAlreadyClosedException::withId((string) $this->id);
    }

    if (!$this->status->isDraft()) {
      throw InspectionAlreadySubmittedException::withId((string) $this->id);
    }

    if ($references->hasEquipmentId && null !== $references->equipmentId) {
      $this->equipmentId = $references->equipmentId;
    }

    if ($references->hasFacilityId) {
      $this->facilityId = $references->facilityId;
    }

    if ($references->hasChecklistId) {
      $this->checklistId = $references->checklistId;
    }

    if ($finding->hasResult && null !== $finding->result) {
      $this->result = $finding->result;
    }

    if ($finding->hasPerformedAt && null !== $finding->performedAt) {
      $this->performedAt = $finding->performedAt;
    }

    if ($finding->text?->hasNotes) {
      $this->notes = self::normalizeText($finding->text->notes, 'notes', 5000);
    }

    if ($finding->text?->hasSignature) {
      $this->signature = $finding->text->signature;
    }

    $this->touch();
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): InspectionId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): InspectionOrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method equipmentId.
   *
   * @since 1.0.0
   */
  public function equipmentId(): InspectionEquipmentId
  {
    return $this->equipmentId;
  }

  /**
   * Method facilityId.
   *
   * @since 1.0.0
   */
  public function facilityId(): ?InspectionFacilityId
  {
    return $this->facilityId;
  }

  /**
   * Method inspector.
   *
   * @since 1.0.0
   */
  public function inspector(): Inspector
  {
    return $this->inspector;
  }

  /**
   * Method result.
   *
   * @since 1.0.0
   */
  public function result(): InspectionResult
  {
    return $this->result;
  }

  /**
   * Method status.
   *
   * @since 1.0.0
   */
  public function status(): InspectionStatus
  {
    return $this->status;
  }

  /**
   * Method performedAt.
   *
   * @since 1.0.0
   */
  public function performedAt(): DateTimeImmutable
  {
    return $this->performedAt;
  }

  /**
   * Method checklistId.
   *
   * @since 1.0.0
   */
  public function checklistId(): ?InspectionChecklistId
  {
    return $this->checklistId;
  }

  /**
   * Method notes.
   *
   * @since 1.0.0
   */
  public function notes(): ?string
  {
    return $this->notes;
  }

  /**
   * Method signature.
   *
   * @since 1.0.0
   */
  public function signature(): ?string
  {
    return $this->signature;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * @since 1.0.0
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method touch.
   *
   * Updates the last modification timestamp.
   *
   * @since 1.0.0
   */
  private function touch(): void
  {
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method normalizeText.
   *
   * @since 1.0.0
   *
   * @param ?string $value the raw value
   * @param string $fieldName the field name for error messages
   * @param int $maxLength the maximum allowed length
   *
   * @return ?string the normalized value
   */
  private static function normalizeText(?string $value, string $fieldName, int $maxLength): ?string
  {
    if (null === $value) {
      return null;
    }

    $normalized = trim($value);
    if ('' === $normalized) {
      return null;
    }

    if (mb_strlen($normalized) > $maxLength) {
      throw InvalidValueException::because(
        sprintf('Inspection %s must be at most %d characters.', $fieldName, $maxLength),
      );
    }

    return $normalized;
  }
  // #endregion
}
