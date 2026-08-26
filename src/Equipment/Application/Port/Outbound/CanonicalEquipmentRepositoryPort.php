<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

use Equipment\Domain\Model\Equipment\CanonicalEquipment;
use Equipment\Domain\ValueObject\EquipmentId;

/**
 * Port CanonicalEquipmentRepositoryPort.
 *
 * The offline-sync half of the `equipment` table — the three columns
 * `EquipmentRepositoryPort` does not carry (`record_status`,
 * `intervention_id`, `revision`) plus the fields the canonical surface
 * mutates.
 *
 * Separate from `EquipmentRepositoryPort` on purpose: saving the aggregate
 * cannot bump the revision the canonical `If-Match` contract is built on.
 * See {@see CanonicalEquipment} for why the two models coexist.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CanonicalEquipmentRepositoryPort
{
  // #region Methods
  /**
   * Method findById.
   *
   * Finds a canonical equipment by identifier, published or scratchpad
   * alike.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $id the equipment identifier
   *
   * @return ?CanonicalEquipment the canonical equipment when found
   */
  public function findById(EquipmentId $id): ?CanonicalEquipment;

  /**
   * Method save.
   *
   * Persists the canonical columns of an existing row. Never inserts:
   * canonical rows are born through the `Equipment` aggregate.
   *
   * @since 1.0.0
   *
   * @param CanonicalEquipment $equipment the canonical equipment
   */
  public function save(CanonicalEquipment $equipment): void;

  /**
   * Method delete.
   *
   * Hard-deletes a row. Reached only for a draft scratchpad — a published
   * asset is decommissioned, never removed. A missing row is not an error.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $id the equipment identifier
   */
  public function delete(EquipmentId $id): void;
  // #endregion
}
