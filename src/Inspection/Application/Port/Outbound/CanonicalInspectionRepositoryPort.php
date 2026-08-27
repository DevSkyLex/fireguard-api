<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Application\Contract\Inspection\CanonicalInspectionReadView;
use Inspection\Domain\Model\Inspection\CanonicalInspection;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId};

/**
 * Port CanonicalInspectionRepositoryPort.
 *
 * The offline-sync half of the `inspections` table — the three columns
 * `InspectionRepositoryPort` deliberately does not carry
 * (`record_status`, `intervention_id`, `revision`) plus the fields the
 * canonical surface mutates.
 *
 * Separate from `InspectionRepositoryPort` on purpose: `save(Inspection)`
 * leaves those three columns untouched, so it cannot bump the revision the
 * canonical `If-Match` contract is built on. See
 * {@see CanonicalInspection} for why the two models coexist.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CanonicalInspectionRepositoryPort
{
  // #region Methods
  /**
   * Method findById.
   *
   * Finds a canonical inspection by identifier, published or scratchpad
   * alike.
   *
   * @since 1.0.0
   *
   * @param InspectionId $id the inspection identifier
   *
   * @return ?CanonicalInspection the canonical inspection when found
   */
  public function findById(InspectionId $id): ?CanonicalInspection;

  /**
   * Method save.
   *
   * Persists the canonical columns of an existing row. Never inserts:
   * canonical rows are born through the `Inspection` aggregate.
   *
   * @since 1.0.0
   *
   * @param CanonicalInspection $inspection the canonical inspection
   */
  public function save(CanonicalInspection $inspection): void;

  /**
   * Method delete.
   *
   * Hard-deletes a row. Reached only for a draft scratchpad — a published
   * inspection is cancelled, never removed. A missing row is not an error.
   *
   * @since 1.0.0
   *
   * @param InspectionId $id the inspection identifier
   */
  public function delete(InspectionId $id): void;

  /**
   * Method findReadById.
   *
   * Projects one row onto the read view the canonical GET answers with.
   *
   * Distinct from `findById()`: that one hydrates the mutation model, which
   * carries neither `performed_at` nor the inspector quartet and carries
   * invariants a read has no use for.
   *
   * The returned view's `nonConformitiesCount` is always 0 — the caller fills
   * it from one grouped query, never from a lazy association.
   *
   * @since 1.0.0
   *
   * @param InspectionId $id the inspection identifier
   *
   * @return ?CanonicalInspectionReadView the read view when the row exists
   */
  public function findReadById(InspectionId $id): ?CanonicalInspectionReadView;

  /**
   * Method findReadByFilters.
   *
   * Lists an organization's inspections, oldest first, optionally narrowed to
   * one intervention and/or one equipment item.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the owning organization
   * @param ?string $interventionId narrow to one intervention, or null
   * @param ?string $equipmentId narrow to one equipment item, or null
   * @param string $recordStatus the representation lifecycle status
   * @param int $limit the page size
   * @param int $offset the page offset
   *
   * @return list<CanonicalInspectionReadView> the page of read views
   */
  public function findReadByFilters(
    InspectionOrganizationId $organizationId,
    ?string $interventionId,
    ?string $equipmentId,
    string $recordStatus,
    int $limit,
    int $offset,
  ): array;

  /**
   * Method countReadByFilters.
   *
   * Counts the rows `findReadByFilters()` would page over, with the same
   * filters and without projecting them.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the owning organization
   * @param ?string $interventionId narrow to one intervention, or null
   * @param ?string $equipmentId narrow to one equipment item, or null
   * @param string $recordStatus the representation lifecycle status
   *
   * @return int the total row count
   */
  public function countReadByFilters(
    InspectionOrganizationId $organizationId,
    ?string $interventionId,
    ?string $equipmentId,
    string $recordStatus,
  ): int;
  // #endregion
}
