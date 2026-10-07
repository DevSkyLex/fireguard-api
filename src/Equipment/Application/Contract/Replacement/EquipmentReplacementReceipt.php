<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Replacement;

/**
 * Class EquipmentReplacementReceipt
 *
 * Durable result and normalized payload identity for an organization-scoped operation.
 *
 * @category Contract
 */
final readonly class EquipmentReplacementReceipt
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $organizationId the owner
   * @param string $clientOperationId the caller's stable replay identity
   * @param string $predecessorEquipmentId the retired asset
   * @param string $successorEquipmentId the replacement asset
   * @param string $payloadHash the canonical request fingerprint
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $clientOperationId,
    public string $predecessorEquipmentId,
    public string $successorEquipmentId,
    public string $payloadHash,
  ) {
  }
  // #endregion
}
