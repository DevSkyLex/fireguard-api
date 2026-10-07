<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

use Equipment\Application\Contract\Replacement\EquipmentReplacementReceipt;
use Equipment\Domain\Model\Replacement\{EquipmentReplacement, ReplacementEquipment};

/**
 * Interface EquipmentReplacementRepositoryPort
 *
 * All methods run inside one main transaction; locks survive until commit.
 *
 * @category Port
 */
interface EquipmentReplacementRepositoryPort
{
  // #region Methods
  /**
   * Method findReceiptForUpdate
   *
   * Serializes a caller operation before looking up its receipt, including absent receipts.
   *
   * @access public
   *
   * @param string $organizationId the owner
   * @param string $clientOperationId the stable operation identity
   *
   * @return ?EquipmentReplacementReceipt the already committed result
   */
  public function findReceiptForUpdate(string $organizationId, string $clientOperationId): ?EquipmentReplacementReceipt;

  /**
   * Method lockEquipment
   *
   * Locks only owner-scoped equipment, in deterministic identifier order.
   *
   * @access public
   *
   * @param string $organizationId the owner
   * @param list<string> $equipmentIds the identities to lock
   *
   * @return array<string, ReplacementEquipment> available owner-scoped candidates keyed by identity
   */
  public function lockEquipment(string $organizationId, array $equipmentIds): array;

  /**
   * Method save
   *
   * Applies lifecycle/placement/link writes and persists the receipt atomically.
   *
   * @access public
   *
   * @param EquipmentReplacement $replacement the validated replacement
   * @param EquipmentReplacementReceipt $receipt the durable operation result
   *
   * @return void
   */
  public function save(EquipmentReplacement $replacement, EquipmentReplacementReceipt $receipt): void;
  // #endregion
}
