<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

use Facility\Domain\Model\Facility\CanonicalFacility;
use Facility\Domain\ValueObject\FacilityId;

/**
 * Port CanonicalFacilityRepositoryPort.
 *
 * The offline-sync half of the `facilities` table — the three columns
 * `FacilityRepositoryPort` does not carry (`record_status`,
 * `intervention_id`, `revision`) plus the fields the canonical surface
 * mutates, and the two hierarchy reads its guards need.
 *
 * Separate from `FacilityRepositoryPort` on purpose: saving the aggregate
 * cannot bump the revision the canonical `If-Match` contract is built on.
 * See {@see CanonicalFacility} for why the two models coexist.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CanonicalFacilityRepositoryPort
{
  // #region Methods
  /**
   * Method findById.
   *
   * Finds a canonical facility by identifier, published or scratchpad alike.
   *
   * @since 1.0.0
   *
   * @param FacilityId $id the facility identifier
   *
   * @return ?CanonicalFacility the canonical facility when found
   */
  public function findById(FacilityId $id): ?CanonicalFacility;

  /**
   * Method save.
   *
   * Persists the canonical columns of an existing row. Never inserts:
   * canonical rows are born through the `Facility` aggregate.
   *
   * @since 1.0.0
   *
   * @param CanonicalFacility $facility the canonical facility
   */
  public function save(CanonicalFacility $facility): void;

  /**
   * Method delete.
   *
   * Hard-deletes a row. Reached only for a childless draft scratchpad — a
   * published facility is archived, never removed. A missing row is not an
   * error.
   *
   * @since 1.0.0
   *
   * @param FacilityId $id the facility identifier
   */
  public function delete(FacilityId $id): void;

  /**
   * Method countChildren.
   *
   * Counts the facilities whose parent is this one.
   *
   * The canonical DELETE needs it because the foreign key is
   * `ON DELETE SET NULL`: hard-deleting a parent would silently promote its
   * whole sub-tree to root, with nothing in the response to say so.
   *
   * @since 1.0.0
   *
   * @param FacilityId $id the facility identifier
   *
   * @return int the number of direct children
   */
  public function countChildren(FacilityId $id): int;

  /**
   * Method ancestorIdsOf.
   *
   * Walks a facility's parent chain and returns the ancestor identifiers,
   * nearest first, excluding the facility itself.
   *
   * Backs the cycle guard: a proposed parent that has the facility being
   * moved among its ancestors is one of that facility's own descendants.
   *
   * @since 1.0.0
   *
   * @param FacilityId $id the facility identifier
   *
   * @return list<string> the ancestor identifiers, nearest first
   */
  public function ancestorIdsOf(FacilityId $id): array;
  // #endregion
}
