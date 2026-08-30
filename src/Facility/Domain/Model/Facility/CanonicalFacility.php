<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Facility;

use DateTimeImmutable;
use Facility\Domain\Exception\{CanonicalFacilityValidationException, FacilityRevisionMismatchException};
use Facility\Domain\ValueObject\{
  CanonicalFacilityChange,
  CanonicalFacilityParent,
  CanonicalFacilityPatch,
  FacilityId,
  FacilityLevelIndex,
  FacilityOrganizationId,
  FacilityRecordStatus,
  FacilityStatus,
  FacilityType
};
use Shared\Domain\Exception\InvalidValueException;

use function trim;

/**
 * Model CanonicalFacility.
 *
 * The offline-syncable view of a facility row, and the rules the flat
 * `/api/facilities/{id}` surface applies to it.
 *
 * **Why this is not `Facility`.** The two model the same table on purpose and
 * do not overlap: the aggregate drives the organization-scoped commands and
 * does not carry `record_status`, `intervention_id` or `revision`, so saving
 * it can never bump the revision the canonical `If-Match` contract is built
 * on. The same split exists in the Inspection and Equipment modules —
 * `src/Inspection/MODULE.md` carries the long-form account.
 *
 * **What this model does NOT decide**, because it cannot: whether the
 * proposed parent exists and belongs to the organization, whether it is one
 * of this facility's own descendants, whether the move fits under the depth
 * cap, whether the organization's metadata schema accepts the payload, and
 * whether archiving would orphan a live dependent. All five need a
 * repository or another module; the handler runs them, in the order the
 * processor ran them, and hands the result in.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CanonicalFacility
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param FacilityId $id the facility identifier
   * @param FacilityOrganizationId $organizationId the owning organization identifier
   * @param FacilityRecordStatus $recordStatus whether the row is published or a scratchpad
   * @param ?string $interventionId the preparing intervention identifier
   * @param ?string $parentFacilityId the parent facility identifier
   * @param FacilityType $type the facility type
   * @param string $name the facility name
   * @param ?string $code the human-facing code
   * @param ?string $address the postal address
   * @param ?float $latitude the latitude
   * @param ?float $longitude the longitude
   * @param array<string, mixed> $metadata the typed metadata map
   * @param FacilityStatus $status the facility lifecycle status
   * @param int $revision the optimistic-concurrency revision
   * @param DateTimeImmutable $updatedAt the last mutation timestamp
   * @param ?int $levelIndex the stacking order of the floor (ground floor = 0, first basement = -1)
   */
  private function __construct(
    private FacilityId $id,
    private FacilityOrganizationId $organizationId,
    private FacilityRecordStatus $recordStatus,
    private ?string $interventionId,
    private ?string $parentFacilityId,
    private FacilityType $type,
    private string $name,
    private ?string $code,
    private ?string $address,
    private ?float $latitude,
    private ?float $longitude,
    private array $metadata,
    private FacilityStatus $status,
    private int $revision,
    private DateTimeImmutable $updatedAt,
    private ?int $levelIndex = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method reconstitute.
   *
   * Reconstitutes a canonical facility from persisted state. There is no
   * `create()`: canonical rows are born through `CreateFacilityProcessor` and
   * the `Facility` aggregate, never here.
   *
   * @since 1.0.0
   *
   * @param FacilityId $id the facility identifier
   * @param FacilityOrganizationId $organizationId the owning organization identifier
   * @param FacilityRecordStatus $recordStatus whether the row is published or a scratchpad
   * @param ?string $interventionId the preparing intervention identifier
   * @param ?string $parentFacilityId the parent facility identifier
   * @param FacilityType $type the facility type
   * @param string $name the facility name
   * @param ?string $code the human-facing code
   * @param ?string $address the postal address
   * @param ?float $latitude the latitude
   * @param ?float $longitude the longitude
   * @param array<string, mixed> $metadata the typed metadata map
   * @param FacilityStatus $status the facility lifecycle status
   * @param int $revision the optimistic-concurrency revision
   * @param DateTimeImmutable $updatedAt the last mutation timestamp
   * @param ?int $levelIndex the stacking order of the floor (ground floor = 0, first basement = -1)
   *
   * @return self the reconstituted canonical facility
   */
  public static function reconstitute(
    FacilityId $id,
    FacilityOrganizationId $organizationId,
    FacilityRecordStatus $recordStatus,
    ?string $interventionId,
    ?string $parentFacilityId,
    FacilityType $type,
    string $name,
    ?string $code,
    ?string $address,
    ?float $latitude,
    ?float $longitude,
    array $metadata,
    FacilityStatus $status,
    int $revision,
    DateTimeImmutable $updatedAt,
    ?int $levelIndex = null,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      recordStatus: $recordStatus,
      interventionId: $interventionId,
      parentFacilityId: $parentFacilityId,
      type: $type,
      name: $name,
      code: $code,
      address: $address,
      latitude: $latitude,
      longitude: $longitude,
      metadata: $metadata,
      status: $status,
      revision: $revision,
      updatedAt: $updatedAt,
      levelIndex: self::normalizeLevelIndex($levelIndex),
    );
  }

  /**
   * Method applyPatch.
   *
   * Applies one merge patch, bumps the revision, and reports what actually
   * changed.
   *
   * @since 1.0.0
   *
   * @param CanonicalFacilityPatch $patch the requested changes, already validated field by field
   * @param ?CanonicalFacilityParent $parent the resolved new parent, when `parent` was sent non-null
   *
   * @throws CanonicalFacilityValidationException when a published facility is restored under an archived parent
   *
   * @return CanonicalFacilityChange what changed — empty for a scratchpad row
   */
  public function applyPatch(CanonicalFacilityPatch $patch, ?CanonicalFacilityParent $parent = null): CanonicalFacilityChange
  {
    $previousStatus = $this->status;
    $previousParentFacilityId = $this->parentFacilityId;
    // Captured before any assignment so the changed-field list reflects what
    // actually DIFFERS, not merely which keys the body carried: resending the
    // current value stays a no-op, exactly like a same-parent move.
    $previous = [
      'type' => $this->type,
      'name' => $this->name,
      'code' => $this->code,
      'address' => $this->address,
      'latitude' => $this->latitude,
      'longitude' => $this->longitude,
      'metadata' => $this->metadata,
      'levelIndex' => $this->levelIndex,
    ];

    if ($patch->hasType && null !== $patch->type) {
      $this->type = FacilityType::tryFrom($patch->type)
        ?? throw CanonicalFacilityValidationException::unsupportedValue('type', $patch->type);
    }

    if ($patch->hasName && null !== $patch->name) {
      $this->name = trim($patch->name);
    }

    if ($patch->hasCode) {
      $this->code = null === $patch->code ? null : trim($patch->code);
    }

    if ($patch->hasAddress) {
      $this->address = null === $patch->address ? null : trim($patch->address);
    }

    if ($patch->hasLatitude || $patch->hasLongitude) {
      $this->latitude = $patch->latitude;
      $this->longitude = $patch->longitude;
    }

    if ($patch->hasMetadata) {
      $this->metadata = $patch->metadata ?? [];
    }

    if ($patch->hasLevelIndex) {
      $this->levelIndex = self::normalizeLevelIndex($patch->levelIndex);
    }

    if ($patch->hasStatus && null !== $patch->status) {
      $this->status = FacilityStatus::tryFrom($patch->status)
        ?? throw CanonicalFacilityValidationException::unsupportedValue('status', $patch->status);
    }

    if ($patch->hasParent) {
      $this->parentFacilityId = $parent?->id;
    }

    $published = FacilityRecordStatus::PUBLISHED === $this->recordStatus;

    // Reactivating a facility into an archived subtree would leave it visible
    // under an invisible ancestor. Mirrors the RestoreFacility use case, and
    // reads the NEW parent — a patch that re-parents and restores at once is
    // judged on where it lands.
    if (
      $published
      && FacilityStatus::ARCHIVED === $previousStatus
      && FacilityStatus::ACTIVE === $this->status
      && null !== $parent
      && FacilityStatus::ARCHIVED === $parent->status
    ) {
      throw CanonicalFacilityValidationException::cannotRestoreUnderAnArchivedParent();
    }

    ++$this->revision;
    $this->updatedAt = new DateTimeImmutable();

    if (!$published) {
      return new CanonicalFacilityChange();
    }

    $changedFields = [];
    foreach (['type', 'name', 'code', 'address', 'levelIndex'] as $field) {
      if ($previous[$field] !== $this->{$field}) {
        $changedFields[] = $field;
      }
    }
    if ($previous['latitude'] !== $this->latitude || $previous['longitude'] !== $this->longitude) {
      $changedFields[] = 'coordinates';
    }
    if ($previous['metadata'] !== $this->metadata) {
      $changedFields[] = 'metadata';
    }

    return new CanonicalFacilityChange(
      archived: FacilityStatus::ARCHIVED !== $previousStatus && FacilityStatus::ARCHIVED === $this->status,
      restored: FacilityStatus::ARCHIVED === $previousStatus && FacilityStatus::ACTIVE === $this->status,
      parentMoved: $this->parentFacilityId !== $previousParentFacilityId,
      previousParentFacilityId: $previousParentFacilityId,
      newParentFacilityId: $this->parentFacilityId,
      changedFields: $changedFields,
    );
  }

  /**
   * Method wouldRestore.
   *
   * Tells whether this patch would take a published, archived facility back
   * to `active` — the only case in which the EFFECTIVE parent's status
   * matters, and therefore the only case in which the handler has to resolve
   * a parent the patch never mentioned.
   *
   * @since 1.0.0
   *
   * @param CanonicalFacilityPatch $patch the requested changes
   *
   * @return bool true when the patch is a restore
   */
  public function wouldRestore(CanonicalFacilityPatch $patch): bool
  {
    return FacilityRecordStatus::PUBLISHED === $this->recordStatus
      && FacilityStatus::ARCHIVED === $this->status
      && $patch->hasStatus
      && FacilityStatus::ACTIVE->value === $patch->status;
  }

  /**
   * Method archive.
   *
   * Retires a published facility. `archived` is the only REVERSIBLE
   * retirement state across the three canonical surfaces — a facility can be
   * restored, an inspection's `cancelled` and an asset's `decommissioned`
   * cannot. A repeat call is an idempotent no-op that does NOT bump the
   * revision.
   *
   * The caller must have run the archival guard first: this model cannot know
   * whether a live dependent still points at the facility.
   *
   * @since 1.0.0
   *
   * @return bool true when the facility was archived, false when it already was
   */
  public function archive(): bool
  {
    if (FacilityStatus::ARCHIVED === $this->status) {
      return false;
    }

    $this->status = FacilityStatus::ARCHIVED;
    ++$this->revision;
    $this->updatedAt = new DateTimeImmutable();

    return true;
  }

  /**
   * Method isAlreadyArchived.
   *
   * Lets the DELETE handler skip the archival guard on a repeat call: an
   * idempotent no-op must stay a no-op, not start failing because a dependent
   * appeared after the facility was retired.
   *
   * @since 1.0.0
   *
   * @return bool true when the facility is already archived
   */
  public function isAlreadyArchived(): bool
  {
    return FacilityStatus::ARCHIVED === $this->status;
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
   * @throws FacilityRevisionMismatchException when the stored revision moved on
   */
  public function assertRevisionMatches(int $expectedRevision): void
  {
    if ($this->revision !== $expectedRevision) {
      throw FacilityRevisionMismatchException::stale();
    }
  }

  /**
   * Method isScratchpad.
   *
   * Tells whether the row exists only inside an intervention being prepared —
   * in which case the canonical DELETE hard-deletes it rather than archiving
   * it, and nothing reaches the audit ledger.
   *
   * @since 1.0.0
   *
   * @return bool true when the record is a draft scratchpad
   */
  public function isScratchpad(): bool
  {
    return FacilityRecordStatus::DRAFT === $this->recordStatus;
  }

  /**
   * Method isPublished.
   *
   * @since 1.0.0
   */
  public function isPublished(): bool
  {
    return FacilityRecordStatus::PUBLISHED === $this->recordStatus;
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): FacilityId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): FacilityOrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method recordStatus.
   *
   * @since 1.0.0
   */
  public function recordStatus(): FacilityRecordStatus
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
   * Method parentFacilityId.
   *
   * @since 1.0.0
   */
  public function parentFacilityId(): ?string
  {
    return $this->parentFacilityId;
  }

  /**
   * Method type.
   *
   * @since 1.0.0
   */
  public function type(): FacilityType
  {
    return $this->type;
  }

  /**
   * Method name.
   *
   * @since 1.0.0
   */
  public function name(): string
  {
    return $this->name;
  }

  /**
   * Method code.
   *
   * @since 1.0.0
   */
  public function code(): ?string
  {
    return $this->code;
  }

  /**
   * Method address.
   *
   * @since 1.0.0
   */
  public function address(): ?string
  {
    return $this->address;
  }

  /**
   * Method latitude.
   *
   * @since 1.0.0
   */
  public function latitude(): ?float
  {
    return $this->latitude;
  }

  /**
   * Method longitude.
   *
   * @since 1.0.0
   */
  public function longitude(): ?float
  {
    return $this->longitude;
  }

  /**
   * Method metadata.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed> the typed metadata map
   */
  public function metadata(): array
  {
    return $this->metadata;
  }

  /**
   * Method levelIndex.
   *
   * @since 1.0.0
   */
  public function levelIndex(): ?int
  {
    return $this->levelIndex;
  }

  /**
   * Method status.
   *
   * @since 1.0.0
   */
  public function status(): FacilityStatus
  {
    return $this->status;
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
   * Method normalizeLevelIndex.
   *
   * Delegates to {@see FacilityLevelIndex::normalize()} so the flat PATCH
   * surface enforces exactly the bound `Facility` enforces, from one
   * declaration rather than a copy that could drift.
   *
   * @since 1.0.0
   *
   * @throws InvalidValueException when the level index is out of range
   */
  private static function normalizeLevelIndex(?int $levelIndex): ?int
  {
    return FacilityLevelIndex::normalize($levelIndex);
  }
  // #endregion
}
