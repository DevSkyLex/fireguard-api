<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\Replacement;

use Equipment\Domain\Exception\EquipmentReplacementConflictException;

/**
 * Class EquipmentReplacement
 *
 * Validates one replacement without transferring the predecessor's historical identity.
 *
 * @category Model
 */
final readonly class EquipmentReplacement
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param ReplacementEquipment $predecessor the active historical asset
   * @param ReplacementEquipment $successor the unused stock asset
   *
   * @return void
   */
  public function __construct(
    public ReplacementEquipment $predecessor,
    public ReplacementEquipment $successor,
  ) {
    $predecessor->assertReplaceable();
    if ($predecessor->id === $successor->id || $predecessor->organizationId !== $successor->organizationId) {
      throw EquipmentReplacementConflictException::because('Replacement requires two distinct equipment items in the same organization.');
    }
    if ('published' !== $successor->recordStatus || 'in_stock' !== $successor->status || null !== $successor->predecessorId || null !== $successor->successorId) {
      throw EquipmentReplacementConflictException::because('The successor must be published, in stock and outside another replacement chain.');
    }
    if ('in_stock' !== $predecessor->status && null === $predecessor->facilityId) {
      throw EquipmentReplacementConflictException::because('An in-service predecessor must be assigned to a facility.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method successorStatus
   *
   * @access public
   *
   * @return string the successor lifecycle after replacing the predecessor
   */
  public function successorStatus(): string
  {
    return 'in_stock' === $this->predecessor->status ? 'in_stock' : 'operational';
  }
  // #endregion
}
