<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Approval;

use Approval\Application\Contract\Action\{ApprovalActionDescriptor, ApprovalActionTypes};
use Approval\Application\Port\Outbound\ApprovalActionDescriptorProviderPort;

/**
 * Class EquipmentDecommissionDescriptorAdapter
 *
 * Contributes Equipment's existing decommission action metadata.
 *
 * @category Adapter
 */
final readonly class EquipmentDecommissionDescriptorAdapter implements ApprovalActionDescriptorProviderPort
{
  // #region Methods
  /**
   * Method descriptor
   *
   * @access public
   *
   * @return ApprovalActionDescriptor the decommission metadata
   */
  public function descriptor(): ApprovalActionDescriptor
  {
    return new ApprovalActionDescriptor(ApprovalActionTypes::EQUIPMENT_DECOMMISSION, 'Equipment decommission');
  }
  // #endregion
}
