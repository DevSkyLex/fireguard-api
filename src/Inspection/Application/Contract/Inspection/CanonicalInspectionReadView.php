<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Inspection;

use DateTimeImmutable;

/**
 * Contract CanonicalInspectionReadView.
 *
 * The flat read projection of one canonical inspection row — everything
 * `CanonicalInspectionProvider` puts on an `InspectionOutput`, and nothing
 * else.
 *
 * **Deliberately not the `CanonicalInspection` model.** That one exists to be
 * mutated: it carries the columns the canonical PATCH and DELETE may change
 * and the invariants that guard them. A listing needs `performed_at`, the
 * inspector quartet, `facility_id` and `checklist_id`, which the model has no
 * business carrying, and needs none of the invariants. Hydrating a mutation
 * model to answer a read is how a list endpoint ends up paying for a write
 * path.
 *
 * The inspector is flattened into four fields rather than nested: the nesting
 * belongs to `InspectorOutput`, which is transport.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CanonicalInspectionReadView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the inspection identifier
   * @param string $organizationId the owning organization identifier
   * @param ?string $interventionId the preparing intervention identifier
   * @param string $recordStatus `draft` for an intervention scratchpad, `published` otherwise
   * @param int $revision the optimistic-concurrency revision
   * @param string $equipmentId the inspected equipment identifier
   * @param ?string $facilityId the facility the inspection was performed in
   * @param string $result the recorded result
   * @param string $status the inspection lifecycle status
   * @param DateTimeImmutable $performedAt when the inspection was performed
   * @param string $inspectorType `user` or `external`
   * @param ?string $inspectorUserId the inspector's user identifier, for an internal inspector
   * @param string $inspectorName the inspector's display name
   * @param ?string $inspectorOrganizationName the external inspector's firm
   * @param ?string $checklistId the checklist the inspection followed
   * @param ?string $notes the free-form notes
   * @param ?string $signature the inspector signature
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last mutation timestamp
   * @param int $nonConformitiesCount how many deficiencies the inspection recorded
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public ?string $interventionId,
    public string $recordStatus,
    public int $revision,
    public string $equipmentId,
    public ?string $facilityId,
    public string $result,
    public string $status,
    public DateTimeImmutable $performedAt,
    public string $inspectorType,
    public ?string $inspectorUserId,
    public string $inspectorName,
    public ?string $inspectorOrganizationName,
    public ?string $checklistId,
    public ?string $notes,
    public ?string $signature,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public int $nonConformitiesCount = 0,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method withNonConformitiesCount.
   *
   * The count does not come from the inspection row, and must not come from a
   * lazy association either: touching `$record->nonConformities` per row is
   * an N+1, which is exactly the defect L1.10b fixed on the checklist listing.
   * The repository projects the row with a zero, and the handler fills it in
   * from ONE grouped query over the whole page.
   *
   * @since 1.0.0
   *
   * @param int $nonConformitiesCount the count for this inspection
   *
   * @return self a copy carrying the count
   */
  public function withNonConformitiesCount(int $nonConformitiesCount): self
  {
    return new self(
      id: $this->id,
      organizationId: $this->organizationId,
      interventionId: $this->interventionId,
      recordStatus: $this->recordStatus,
      revision: $this->revision,
      equipmentId: $this->equipmentId,
      facilityId: $this->facilityId,
      result: $this->result,
      status: $this->status,
      performedAt: $this->performedAt,
      inspectorType: $this->inspectorType,
      inspectorUserId: $this->inspectorUserId,
      inspectorName: $this->inspectorName,
      inspectorOrganizationName: $this->inspectorOrganizationName,
      checklistId: $this->checklistId,
      notes: $this->notes,
      signature: $this->signature,
      createdAt: $this->createdAt,
      updatedAt: $this->updatedAt,
      nonConformitiesCount: $nonConformitiesCount,
    );
  }
  // #endregion
}
