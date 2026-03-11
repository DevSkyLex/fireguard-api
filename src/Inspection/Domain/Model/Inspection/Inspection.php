<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Inspection;

use DateTimeImmutable;
use Inspection\Domain\Exception\{InspectionAlreadyClosedException, InspectionAlreadySubmittedException, InspectionNotSubmittedException};
use Inspection\Domain\ValueObject\{
  InspectionChecklistId,
  InspectionEquipmentId,
  InspectionFacilityId,
  InspectionId,
  InspectionOrganizationId,
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
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InspectionId $id the inspection identifier
   * @param InspectionOrganizationId $organizationId the organization identifier
   * @param InspectionEquipmentId $equipmentId the equipment identifier
   * @param Inspector $inspector the person who performed the inspection
   * @param InspectionResult $result the inspection result
   * @param InspectionStatus $status the inspection status
   * @param DateTimeImmutable $performedAt when the inspection was performed
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   * @param ?InspectionFacilityId $facilityId the optional facility identifier
   * @param ?InspectionChecklistId $checklistId the optional checklist identifier
   * @param ?string $notes optional free-form notes
   * @param ?string $signature optional signature data
   */
  private function __construct(
    private InspectionId $id,
    private InspectionOrganizationId $organizationId,
    private InspectionEquipmentId $equipmentId,
    private Inspector $inspector,
    private InspectionResult $result,
    private InspectionStatus $status,
    private DateTimeImmutable $performedAt,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private ?InspectionFacilityId $facilityId = null,
    private ?InspectionChecklistId $checklistId = null,
    private ?string $notes = null,
    private ?string $signature = null,
  ) {
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
   * @param ?InspectionFacilityId $facilityId the optional facility identifier
   * @param ?InspectionChecklistId $checklistId the optional checklist identifier
   * @param ?string $notes optional free-form notes
   * @param ?string $signature optional signature data
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
    ?InspectionFacilityId $facilityId = null,
    ?InspectionChecklistId $checklistId = null,
    ?string $notes = null,
    ?string $signature = null,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      organizationId: $organizationId,
      equipmentId: $equipmentId,
      inspector: $inspector,
      result: $result,
      status: InspectionStatus::DRAFT,
      performedAt: $performedAt,
      createdAt: $now,
      updatedAt: $now,
      facilityId: $facilityId,
      checklistId: $checklistId,
      notes: self::normalizeText($notes, 'notes', 5000),
      signature: $signature,
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
   * @param InspectionEquipmentId $equipmentId the equipment identifier
   * @param Inspector $inspector the person who performed the inspection
   * @param InspectionResult $result the inspection result
   * @param InspectionStatus $status the inspection status
   * @param DateTimeImmutable $performedAt when the inspection was performed
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   * @param ?InspectionFacilityId $facilityId the optional facility identifier
   * @param ?InspectionChecklistId $checklistId the optional checklist identifier
   * @param ?string $notes optional free-form notes
   * @param ?string $signature optional signature data
   *
   * @return self the reconstituted inspection aggregate
   */
  public static function reconstitute(
    InspectionId $id,
    InspectionOrganizationId $organizationId,
    InspectionEquipmentId $equipmentId,
    Inspector $inspector,
    InspectionResult $result,
    InspectionStatus $status,
    DateTimeImmutable $performedAt,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?InspectionFacilityId $facilityId = null,
    ?InspectionChecklistId $checklistId = null,
    ?string $notes = null,
    ?string $signature = null,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      equipmentId: $equipmentId,
      inspector: $inspector,
      result: $result,
      status: $status,
      performedAt: $performedAt,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      facilityId: $facilityId,
      checklistId: $checklistId,
      notes: $notes,
      signature: $signature,
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
