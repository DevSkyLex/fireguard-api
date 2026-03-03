<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\NonConformity;

use DateTimeImmutable;
use Inspection\Domain\Exception\NonConformityAlreadyResolvedException;
use Inspection\Domain\ValueObject\{
  NonConformityId,
  NonConformityInspectionId,
  NonConformitySeverity,
  NonConformityStatus
};
use InvalidArgumentException;

use function mb_strlen;
use function sprintf;
use function trim;

/**
 * Model NonConformity.
 *
 * Represents a defect or non-conformity detected during an inspection.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NonConformity
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NonConformityId $id the non-conformity identifier
   * @param NonConformityInspectionId $inspectionId the inspection identifier
   * @param string $description the description of the non-conformity
   * @param NonConformitySeverity $severity the severity level
   * @param NonConformityStatus $status the current status
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   * @param ?DateTimeImmutable $dueAt the optional due date for resolution
   * @param ?DateTimeImmutable $resolvedAt the optional resolution timestamp
   * @param ?string $notes optional free-form notes
   */
  private function __construct(
    private NonConformityId $id,
    private NonConformityInspectionId $inspectionId,
    private string $description,
    private NonConformitySeverity $severity,
    private NonConformityStatus $status,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private ?DateTimeImmutable $dueAt = null,
    private ?DateTimeImmutable $resolvedAt = null,
    private ?string $notes = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new non-conformity in OPEN status.
   *
   * @since 1.0.0
   *
   * @param NonConformityId $id the non-conformity identifier
   * @param NonConformityInspectionId $inspectionId the inspection identifier
   * @param string $description the description
   * @param NonConformitySeverity $severity the severity level
   * @param ?DateTimeImmutable $dueAt the optional due date
   * @param ?string $notes optional notes
   *
   * @return self the created non-conformity
   */
  public static function create(
    NonConformityId $id,
    NonConformityInspectionId $inspectionId,
    string $description,
    NonConformitySeverity $severity,
    ?DateTimeImmutable $dueAt = null,
    ?string $notes = null,
  ): self {
    $normalizedDesc = trim($description);
    if ('' === $normalizedDesc) {
      throw new InvalidArgumentException('Non-conformity description must not be empty.');
    }

    if (mb_strlen($normalizedDesc) > 2000) {
      throw new InvalidArgumentException(
        sprintf('Non-conformity description must be at most %d characters.', 2000),
      );
    }

    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      inspectionId: $inspectionId,
      description: $normalizedDesc,
      severity: $severity,
      status: NonConformityStatus::OPEN,
      createdAt: $now,
      updatedAt: $now,
      dueAt: $dueAt,
      resolvedAt: null,
      notes: self::normalizeText($notes, 'notes', 5000),
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes a non-conformity from persisted state.
   *
   * @since 1.0.0
   *
   * @param NonConformityId $id the non-conformity identifier
   * @param NonConformityInspectionId $inspectionId the inspection identifier
   * @param string $description the description
   * @param NonConformitySeverity $severity the severity level
   * @param NonConformityStatus $status the current status
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   * @param ?DateTimeImmutable $dueAt the optional due date
   * @param ?DateTimeImmutable $resolvedAt the optional resolution timestamp
   * @param ?string $notes optional notes
   *
   * @return self the reconstituted non-conformity
   */
  public static function reconstitute(
    NonConformityId $id,
    NonConformityInspectionId $inspectionId,
    string $description,
    NonConformitySeverity $severity,
    NonConformityStatus $status,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?DateTimeImmutable $dueAt = null,
    ?DateTimeImmutable $resolvedAt = null,
    ?string $notes = null,
  ): self {
    return new self(
      id: $id,
      inspectionId: $inspectionId,
      description: $description,
      severity: $severity,
      status: $status,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      dueAt: $dueAt,
      resolvedAt: $resolvedAt,
      notes: $notes,
    );
  }

  /**
   * Method updateStatus.
   *
   * Transitions the non-conformity to a new status.
   *
   * @since 1.0.0
   *
   * @param NonConformityStatus $status the new status
   */
  public function updateStatus(NonConformityStatus $status): void
  {
    if ($this->status->isResolved()) {
      throw NonConformityAlreadyResolvedException::withId((string) $this->id);
    }

    $this->status = $status;

    if ($status->isResolved()) {
      $this->resolvedAt = new DateTimeImmutable();
    }

    $this->touch();
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): NonConformityId
  {
    return $this->id;
  }

  /**
   * Method inspectionId.
   *
   * @since 1.0.0
   */
  public function inspectionId(): NonConformityInspectionId
  {
    return $this->inspectionId;
  }

  /**
   * Method description.
   *
   * @since 1.0.0
   */
  public function description(): string
  {
    return $this->description;
  }

  /**
   * Method severity.
   *
   * @since 1.0.0
   */
  public function severity(): NonConformitySeverity
  {
    return $this->severity;
  }

  /**
   * Method status.
   *
   * @since 1.0.0
   */
  public function status(): NonConformityStatus
  {
    return $this->status;
  }

  /**
   * Method dueAt.
   *
   * @since 1.0.0
   */
  public function dueAt(): ?DateTimeImmutable
  {
    return $this->dueAt;
  }

  /**
   * Method resolvedAt.
   *
   * @since 1.0.0
   */
  public function resolvedAt(): ?DateTimeImmutable
  {
    return $this->resolvedAt;
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
      throw new InvalidArgumentException(
        sprintf('Non-conformity %s must be at most %d characters.', $fieldName, $maxLength),
      );
    }

    return $normalized;
  }
  // #endregion
}
