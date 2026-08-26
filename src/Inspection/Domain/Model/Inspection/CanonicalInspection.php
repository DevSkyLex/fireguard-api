<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Inspection;

use DateTimeImmutable;
use Inspection\Domain\Exception\{CanonicalInspectionConflictException, CanonicalInspectionValidationException, InspectionRevisionMismatchException};
use Inspection\Domain\ValueObject\{
  CanonicalInspectionPatch,
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionRecordStatus,
  InspectionResult,
  InspectionStatus
};

use function in_array;

/**
 * Model CanonicalInspection.
 *
 * The offline-syncable view of an inspection row, and the rules the flat
 * `/api/inspections/{id}` surface applies to it.
 *
 * **Why this is not `Inspection`.** The two model the same table on purpose
 * and do not overlap:
 *
 * - `Inspection` is the aggregate the organization-scoped commands drive
 *   (`submit`, `close`, `cancel`, `edit`). It is loaded through
 *   `findPublishedById()`, so it never sees a record that exists only inside
 *   an unpublished intervention, and it does not carry `record_status`,
 *   `intervention_id` or `revision` at all — `InspectionRepository::save()`
 *   deliberately leaves those three columns untouched.
 * - `CanonicalInspection` carries exactly those three columns, and applies the
 *   canonical surface's own rules, which differ from the aggregate's and did
 *   so before this model existed:
 *   - a **submitted** record may still have its `result`, `notes` and
 *     `signature` patched here, where `Inspection::edit()` rejects anything
 *     past draft;
 *   - an illegal status jump answers **422** here and **409** through the
 *     aggregate;
 *   - a **draft record** (an intervention scratchpad) skips the lifecycle
 *     entirely — it is a preparation, not a compliance record.
 *
 * That divergence is inherited, not introduced. Reconciling it changes
 * published statuses and belongs to its own decision; `src/Inspection/MODULE.md`
 * records it as debt.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CanonicalInspection
{
  // #region Constants
  /**
   * Legal status transitions for a PUBLISHED record, mirroring the
   * `Inspection` aggregate: draft → submitted → closed, plus the logical
   * annulment draft/submitted → cancelled. `closed` and `cancelled` are
   * terminal.
   *
   * @since 1.0.0
   *
   * @var array<string, list<string>>
   */
  private const array ALLOWED_STATUS_TRANSITIONS = [
    'draft' => ['submitted', 'cancelled'],
    'submitted' => ['closed', 'cancelled'],
    'closed' => [],
    'cancelled' => [],
  ];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InspectionId $id the inspection identifier
   * @param InspectionOrganizationId $organizationId the owning organization identifier
   * @param InspectionEquipmentId $equipmentId the inspected equipment identifier
   * @param InspectionRecordStatus $recordStatus whether the row is published or an intervention scratchpad
   * @param ?string $interventionId the preparing intervention identifier
   * @param InspectionStatus $status the inspection lifecycle status
   * @param InspectionResult $result the recorded result
   * @param ?string $notes the free-form notes
   * @param ?string $signature the inspector signature
   * @param int $revision the optimistic-concurrency revision
   * @param DateTimeImmutable $updatedAt the last mutation timestamp
   */
  private function __construct(
    private InspectionId $id,
    private InspectionOrganizationId $organizationId,
    private InspectionEquipmentId $equipmentId,
    private InspectionRecordStatus $recordStatus,
    private ?string $interventionId,
    private InspectionStatus $status,
    private InspectionResult $result,
    private ?string $notes,
    private ?string $signature,
    private int $revision,
    private DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method reconstitute.
   *
   * Reconstitutes a canonical inspection from persisted state. There is no
   * `create()`: canonical rows are born through `CreateInspectionProcessor`
   * and the `Inspection` aggregate, never here.
   *
   * @since 1.0.0
   *
   * @param InspectionId $id the inspection identifier
   * @param InspectionOrganizationId $organizationId the owning organization identifier
   * @param InspectionEquipmentId $equipmentId the inspected equipment identifier
   * @param InspectionRecordStatus $recordStatus whether the row is published or a scratchpad
   * @param ?string $interventionId the preparing intervention identifier
   * @param InspectionStatus $status the inspection lifecycle status
   * @param InspectionResult $result the recorded result
   * @param ?string $notes the free-form notes
   * @param ?string $signature the inspector signature
   * @param int $revision the optimistic-concurrency revision
   * @param DateTimeImmutable $updatedAt the last mutation timestamp
   *
   * @return self the reconstituted canonical inspection
   */
  public static function reconstitute(
    InspectionId $id,
    InspectionOrganizationId $organizationId,
    InspectionEquipmentId $equipmentId,
    InspectionRecordStatus $recordStatus,
    ?string $interventionId,
    InspectionStatus $status,
    InspectionResult $result,
    ?string $notes,
    ?string $signature,
    int $revision,
    DateTimeImmutable $updatedAt,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      equipmentId: $equipmentId,
      recordStatus: $recordStatus,
      interventionId: $interventionId,
      status: $status,
      result: $result,
      notes: $notes,
      signature: $signature,
      revision: $revision,
      updatedAt: $updatedAt,
    );
  }

  /**
   * Method applyPatch.
   *
   * Applies one merge patch and bumps the revision.
   *
   * The field order is load-bearing: `result` is validated before `status`,
   * so a patch sending both as null is told about `result` — the wording the
   * processor emitted.
   *
   * @since 1.0.0
   *
   * @param CanonicalInspectionPatch $patch the requested changes
   *
   * @throws CanonicalInspectionConflictException when the inspection is closed or cancelled
   * @throws CanonicalInspectionValidationException on a null non-nullable field or an illegal transition
   *
   * @return ?string the previous status when a PUBLISHED record's status changed — the audit trigger — null otherwise
   */
  public function applyPatch(CanonicalInspectionPatch $patch): ?string
  {
    if (InspectionStatus::CLOSED === $this->status || InspectionStatus::CANCELLED === $this->status) {
      throw CanonicalInspectionConflictException::terminalStateIsImmutable();
    }

    $previousStatus = $this->status;

    if ($patch->hasResult) {
      $this->result = self::parseResult($patch->result);
    }

    if ($patch->hasStatus) {
      $this->status = self::parseStatus($patch->status);
    }

    if ($patch->hasNotes) {
      $this->notes = $patch->notes;
    }

    if ($patch->hasSignature) {
      $this->signature = $patch->signature;
    }

    $statusChanged = $this->status !== $previousStatus;

    // A draft record is an intervention scratchpad, not a compliance record:
    // it may move freely and is never audited. Only a published row follows
    // the lifecycle.
    $published = InspectionRecordStatus::PUBLISHED === $this->recordStatus;
    if ($published && $statusChanged) {
      $this->assertLegalStatusTransition($previousStatus, $this->status);
    }

    ++$this->revision;
    $this->updatedAt = new DateTimeImmutable();

    return $published && $statusChanged ? $previousStatus->value : null;
  }

  /**
   * Method cancel.
   *
   * Logically annuls a published inspection: the row and its non-conformities
   * survive, the status becomes `cancelled`. A repeat call is an idempotent
   * no-op that does NOT bump the revision — matching the facility and
   * equipment canonical surfaces.
   *
   * @since 1.0.0
   *
   * @throws CanonicalInspectionConflictException when the inspection is closed
   *
   * @return ?string the previous status when the inspection was cancelled, null when it already was
   */
  public function cancel(): ?string
  {
    if (InspectionStatus::CANCELLED === $this->status) {
      return null;
    }

    if (InspectionStatus::CLOSED === $this->status) {
      throw CanonicalInspectionConflictException::closedCannotBeCancelled();
    }

    $previousStatus = $this->status;
    $this->status = InspectionStatus::CANCELLED;
    ++$this->revision;
    $this->updatedAt = new DateTimeImmutable();

    return $previousStatus->value;
  }

  /**
   * Method assertRevisionMatches.
   *
   * Re-runs the optimistic-concurrency check inside the mutating transaction.
   *
   * @since 1.0.0
   *
   * @param int $expectedRevision the revision the caller declared through `If-Match`
   *
   * @throws InspectionRevisionMismatchException when the stored revision moved on
   */
  public function assertRevisionMatches(int $expectedRevision): void
  {
    if ($this->revision !== $expectedRevision) {
      throw InspectionRevisionMismatchException::stale();
    }
  }

  /**
   * Method isScratchpad.
   *
   * Tells whether the row exists only inside an intervention being prepared —
   * in which case the canonical DELETE hard-deletes it rather than cancelling
   * it, and nothing reaches the audit ledger.
   *
   * @since 1.0.0
   *
   * @return bool true when the record is a draft scratchpad
   */
  public function isScratchpad(): bool
  {
    return InspectionRecordStatus::DRAFT === $this->recordStatus;
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
   * Method recordStatus.
   *
   * @since 1.0.0
   */
  public function recordStatus(): InspectionRecordStatus
  {
    return $this->recordStatus;
  }

  /**
   * Method interventionId.
   *
   * @since 1.0.0
   */
  public function interventionId(): ?string
  {
    return $this->interventionId;
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
   * Method result.
   *
   * @since 1.0.0
   */
  public function result(): InspectionResult
  {
    return $this->result;
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
   * Method revision.
   *
   * @since 1.0.0
   */
  public function revision(): int
  {
    return $this->revision;
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
   * Method parseResult.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param ?string $result the requested result
   *
   * @return InspectionResult the parsed result
   */
  private static function parseResult(?string $result): InspectionResult
  {
    if (null === $result) {
      throw CanonicalInspectionValidationException::fieldCannotBeNull('result');
    }

    return InspectionResult::tryFrom($result)
      ?? throw CanonicalInspectionValidationException::unsupportedValue('result', $result);
  }

  /**
   * Method parseStatus.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param ?string $status the requested status
   *
   * @return InspectionStatus the parsed status
   */
  private static function parseStatus(?string $status): InspectionStatus
  {
    if (null === $status) {
      throw CanonicalInspectionValidationException::fieldCannotBeNull('status');
    }

    return InspectionStatus::tryFrom($status)
      ?? throw CanonicalInspectionValidationException::unsupportedValue('status', $status);
  }

  /**
   * Method assertLegalStatusTransition.
   *
   * @since 1.0.0
   *
   * @param InspectionStatus $from the current status
   * @param InspectionStatus $to the requested status
   */
  private function assertLegalStatusTransition(InspectionStatus $from, InspectionStatus $to): void
  {
    // No `?? []` fallback: the table is keyed by every InspectionStatus case,
    // so a missing key would be a new enum case, and silently allowing every
    // transition out of it is exactly the wrong failure mode. phpstan proves
    // the lookup total.
    if (!in_array($to->value, self::ALLOWED_STATUS_TRANSITIONS[$from->value], true)) {
      throw CanonicalInspectionValidationException::illegalStatusTransition($from->value, $to->value);
    }
  }
  // #endregion
}
