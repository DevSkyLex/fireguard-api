<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\Replacement;

use Equipment\Domain\Exception\EquipmentReplacementConflictException;

/**
 * Class ReplacementEquipment
 *
 * Immutable lifecycle and placement state read under the replacement lock.
 *
 * @category Model
 */
final readonly class ReplacementEquipment
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $id the equipment identity
   * @param string $organizationId the owner identity
   * @param string $recordStatus the publication status
   * @param string $status the equipment lifecycle status
   * @param ?string $facilityId the current assignment
   * @param ?string $locationLabel the declared location
   * @param ?array{attachmentId: string, x: float, y: float} $planPosition the floor-plan position
   * @param ?string $predecessorId the previous asset in the replacement chain
   * @param ?string $successorId the asset already replacing this one
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $recordStatus,
    public string $status,
    public ?string $facilityId,
    public ?string $locationLabel,
    public ?array $planPosition = null,
    public ?string $predecessorId = null,
    public ?string $successorId = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method assertReplaceable
   *
   * @access public
   *
   * @return void
   */
  public function assertReplaceable(): void
  {
    if ('published' !== $this->recordStatus || 'decommissioned' === $this->status || null !== $this->successorId) {
      throw EquipmentReplacementConflictException::because('The predecessor must be a published, active equipment without a successor.');
    }
  }
  // #endregion
}
