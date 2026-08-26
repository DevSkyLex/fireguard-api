<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\Equipment;

use DateTimeImmutable;
use Equipment\Domain\Exception\{CanonicalEquipmentValidationException, EquipmentRevisionMismatchException};
use Equipment\Domain\ValueObject\{
  CanonicalEquipmentPatch,
  EquipmentId,
  EquipmentOrganizationId,
  EquipmentRecordStatus,
  EquipmentStatus
};

use function in_array;

/**
 * Model CanonicalEquipment.
 *
 * The offline-syncable view of an equipment row, and the rules the flat
 * `/api/equipment/{id}` surface applies to it.
 *
 * **Why this is not `Equipment`.** The two model the same table on purpose
 * and do not overlap: the aggregate drives the organization-scoped commands
 * and does not carry `record_status`, `intervention_id` or `revision`, so it
 * cannot bump the revision the canonical `If-Match` contract is built on. The
 * same split exists in the Inspection module — see
 * `Inspection\Domain\Model\Inspection\CanonicalInspection` and
 * `src/Inspection/MODULE.md`.
 *
 * **`type` stays a raw string.** `PatchCanonicalEquipmentInput` constrains it
 * with `#[Assert\Length(max: 32)]`, NOT `#[Assert\Choice]`, so this surface
 * has always accepted a type outside `EquipmentType` and written it through.
 * Narrowing it to the enum here would turn today's 200 into a 422 — a
 * contract change, not a refactor's side effect.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CanonicalEquipment
{
  // #region Constants
  /**
   * Legal status transitions for a PUBLISHED record, mirroring the
   * `Equipment` aggregate. `decommissioned` is terminal — no outgoing edges,
   * an asset is never revived.
   *
   * @since 1.0.0
   *
   * @var array<string, list<string>>
   */
  private const array ALLOWED_STATUS_TRANSITIONS = [
    'in_stock' => ['operational', 'decommissioned'],
    'operational' => ['in_stock', 'under_maintenance', 'decommissioned'],
    'under_maintenance' => ['in_stock', 'operational', 'decommissioned'],
    'decommissioned' => [],
  ];

  /**
   * The statuses that mean the asset is deployed, and therefore require a
   * facility.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  private const array IN_SERVICE_STATUSES = ['operational', 'under_maintenance'];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $id the equipment identifier
   * @param EquipmentOrganizationId $organizationId the owning organization identifier
   * @param EquipmentRecordStatus $recordStatus whether the row is published or a scratchpad
   * @param ?string $interventionId the preparing intervention identifier
   * @param ?string $facilityId the assigned facility identifier
   * @param string $type the equipment type
   * @param ?string $subType the equipment sub-type
   * @param ?string $brand the brand
   * @param ?string $model the model
   * @param ?string $serialNumber the serial number
   * @param ?string $locationLabel the free-form location label
   * @param EquipmentStatus $status the asset lifecycle status
   * @param ?DateTimeImmutable $commissionedAt the first commissioning date
   * @param int $revision the optimistic-concurrency revision
   * @param DateTimeImmutable $updatedAt the last mutation timestamp
   */
  private function __construct(
    private EquipmentId $id,
    private EquipmentOrganizationId $organizationId,
    private EquipmentRecordStatus $recordStatus,
    private ?string $interventionId,
    private ?string $facilityId,
    private string $type,
    private ?string $subType,
    private ?string $brand,
    private ?string $model,
    private ?string $serialNumber,
    private ?string $locationLabel,
    private EquipmentStatus $status,
    private ?DateTimeImmutable $commissionedAt,
    private int $revision,
    private DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method reconstitute.
   *
   * Reconstitutes a canonical equipment from persisted state. There is no
   * `create()`: canonical rows are born through `CreateEquipmentProcessor`
   * and the `Equipment` aggregate, never here.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $id the equipment identifier
   * @param EquipmentOrganizationId $organizationId the owning organization identifier
   * @param EquipmentRecordStatus $recordStatus whether the row is published or a scratchpad
   * @param ?string $interventionId the preparing intervention identifier
   * @param ?string $facilityId the assigned facility identifier
   * @param string $type the equipment type
   * @param ?string $subType the equipment sub-type
   * @param ?string $brand the brand
   * @param ?string $model the model
   * @param ?string $serialNumber the serial number
   * @param ?string $locationLabel the free-form location label
   * @param EquipmentStatus $status the asset lifecycle status
   * @param ?DateTimeImmutable $commissionedAt the first commissioning date
   * @param int $revision the optimistic-concurrency revision
   * @param DateTimeImmutable $updatedAt the last mutation timestamp
   *
   * @return self the reconstituted canonical equipment
   */
  public static function reconstitute(
    EquipmentId $id,
    EquipmentOrganizationId $organizationId,
    EquipmentRecordStatus $recordStatus,
    ?string $interventionId,
    ?string $facilityId,
    string $type,
    ?string $subType,
    ?string $brand,
    ?string $model,
    ?string $serialNumber,
    ?string $locationLabel,
    EquipmentStatus $status,
    ?DateTimeImmutable $commissionedAt,
    int $revision,
    DateTimeImmutable $updatedAt,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      recordStatus: $recordStatus,
      interventionId: $interventionId,
      facilityId: $facilityId,
      type: $type,
      subType: $subType,
      brand: $brand,
      model: $model,
      serialNumber: $serialNumber,
      locationLabel: $locationLabel,
      status: $status,
      commissionedAt: $commissionedAt,
      revision: $revision,
      updatedAt: $updatedAt,
    );
  }

  /**
   * Method applyPatch.
   *
   * Applies one merge patch and bumps the revision.
   *
   * The caller must have run `CanonicalEquipmentPatch::assertNonNullableFieldsArePresent()`
   * and validated `facilityId` against the organization first — in that
   * order, which is the one the processor ran and therefore the one a client
   * sending several invalid fields at once observes.
   *
   * @since 1.0.0
   *
   * @param CanonicalEquipmentPatch $patch the requested changes
   *
   * @throws CanonicalEquipmentValidationException on an in-service row left without a facility, or an illegal transition
   *
   * @return ?string the previous status when a PUBLISHED record's status changed — the audit and maintenance-log trigger — null otherwise
   */
  public function applyPatch(CanonicalEquipmentPatch $patch): ?string
  {
    $previousStatus = $this->status;

    if ($patch->hasType && null !== $patch->type) {
      $this->type = $patch->type;
    }

    if ($patch->hasStatus && null !== $patch->status) {
      $this->status = EquipmentStatus::tryFrom($patch->status)
        ?? throw CanonicalEquipmentValidationException::unsupportedValue('status', $patch->status);
    }

    if ($patch->hasSubType) {
      $this->subType = $patch->subType;
    }

    if ($patch->hasBrand) {
      $this->brand = $patch->brand;
    }

    if ($patch->hasModel) {
      $this->model = $patch->model;
    }

    if ($patch->hasSerialNumber) {
      $this->serialNumber = $patch->serialNumber;
    }

    if ($patch->hasLocationLabel) {
      $this->locationLabel = $patch->locationLabel;
    }

    if ($patch->hasFacility) {
      $this->facilityId = $patch->facilityId;
    }

    // Checked on EVERY patch, not only on a status change: a request that
    // merely clears the facility of an operational asset is exactly the one
    // this rejects.
    if (in_array($this->status->value, self::IN_SERVICE_STATUSES, true) && null === $this->facilityId) {
      throw CanonicalEquipmentValidationException::inServiceRequiresFacility();
    }

    // A draft record is an intervention scratchpad, not a real asset: it may
    // move freely, stamps no commissioning date, syncs no maintenance log and
    // is never audited. Only a published row follows the lifecycle.
    $statusChanged = EquipmentRecordStatus::PUBLISHED === $this->recordStatus && $this->status !== $previousStatus;

    if ($statusChanged) {
      $this->assertLegalStatusTransition($previousStatus, $this->status);

      // Stamp the FIRST commissioning date and preserve it on re-commission,
      // mirroring Equipment::commission().
      if (EquipmentStatus::OPERATIONAL === $this->status) {
        $this->commissionedAt ??= new DateTimeImmutable();
      }
    }

    ++$this->revision;
    $this->updatedAt = new DateTimeImmutable();

    return $statusChanged ? $previousStatus->value : null;
  }

  /**
   * Method decommission.
   *
   * Retires the asset. `decommissioned` is TERMINAL and never reversible —
   * unlike the inspection surface's `cancelled`, there is no path back. A
   * repeat call is an idempotent no-op that does NOT bump the revision,
   * matching the facility and inspection canonical surfaces.
   *
   * @since 1.0.0
   *
   * @return ?string the previous status when the asset was decommissioned, null when it already was
   */
  public function decommission(): ?string
  {
    if (EquipmentStatus::DECOMMISSIONED === $this->status) {
      return null;
    }

    $previousStatus = $this->status;
    $this->status = EquipmentStatus::DECOMMISSIONED;
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
   * @throws EquipmentRevisionMismatchException when the stored revision moved on
   */
  public function assertRevisionMatches(int $expectedRevision): void
  {
    if ($this->revision !== $expectedRevision) {
      throw EquipmentRevisionMismatchException::stale();
    }
  }

  /**
   * Method isScratchpad.
   *
   * Tells whether the row exists only inside an intervention being prepared —
   * in which case the canonical DELETE hard-deletes it rather than
   * decommissioning it, and nothing reaches the audit ledger.
   *
   * @since 1.0.0
   *
   * @return bool true when the record is a draft scratchpad
   */
  public function isScratchpad(): bool
  {
    return EquipmentRecordStatus::DRAFT === $this->recordStatus;
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): EquipmentId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): EquipmentOrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method recordStatus.
   *
   * @since 1.0.0
   */
  public function recordStatus(): EquipmentRecordStatus
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
   * Method facilityId.
   *
   * @since 1.0.0
   */
  public function facilityId(): ?string
  {
    return $this->facilityId;
  }

  /**
   * Method type.
   *
   * @since 1.0.0
   */
  public function type(): string
  {
    return $this->type;
  }

  /**
   * Method subType.
   *
   * @since 1.0.0
   */
  public function subType(): ?string
  {
    return $this->subType;
  }

  /**
   * Method brand.
   *
   * @since 1.0.0
   */
  public function brand(): ?string
  {
    return $this->brand;
  }

  /**
   * Method model.
   *
   * @since 1.0.0
   */
  public function model(): ?string
  {
    return $this->model;
  }

  /**
   * Method serialNumber.
   *
   * @since 1.0.0
   */
  public function serialNumber(): ?string
  {
    return $this->serialNumber;
  }

  /**
   * Method locationLabel.
   *
   * @since 1.0.0
   */
  public function locationLabel(): ?string
  {
    return $this->locationLabel;
  }

  /**
   * Method status.
   *
   * @since 1.0.0
   */
  public function status(): EquipmentStatus
  {
    return $this->status;
  }

  /**
   * Method commissionedAt.
   *
   * @since 1.0.0
   */
  public function commissionedAt(): ?DateTimeImmutable
  {
    return $this->commissionedAt;
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
   * Method assertLegalStatusTransition.
   *
   * @since 1.0.0
   *
   * @param EquipmentStatus $from the current status
   * @param EquipmentStatus $to the requested status
   */
  private function assertLegalStatusTransition(EquipmentStatus $from, EquipmentStatus $to): void
  {
    // No `?? []` fallback: the table is keyed by every EquipmentStatus case,
    // so a missing key would be a new enum case, and silently allowing every
    // transition out of it is exactly the wrong failure mode.
    if (!in_array($to->value, self::ALLOWED_STATUS_TRANSITIONS[$from->value], true)) {
      throw CanonicalEquipmentValidationException::illegalStatusTransition($from->value, $to->value);
    }
  }
  // #endregion
}
