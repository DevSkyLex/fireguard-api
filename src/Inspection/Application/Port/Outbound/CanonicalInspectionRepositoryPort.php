<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Domain\Model\Inspection\CanonicalInspection;
use Inspection\Domain\ValueObject\InspectionId;

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
  // #endregion
}
