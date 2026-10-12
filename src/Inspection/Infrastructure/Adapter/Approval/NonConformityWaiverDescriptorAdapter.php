<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Approval;

use Approval\Application\Contract\Action\{ApprovalActionDescriptor, ApprovalActionTypes};
use Approval\Application\Port\Outbound\ApprovalActionDescriptorProviderPort;

/**
 * Class NonConformityWaiverDescriptorAdapter
 *
 * Contributes Inspection's waiver metadata and its existing critical threshold.
 *
 * @category Adapter
 */
final readonly class NonConformityWaiverDescriptorAdapter implements ApprovalActionDescriptorProviderPort
{
  // #region Methods
  /**
   * Method descriptor
   *
   * @access public
   *
   * @return ApprovalActionDescriptor the non-conformity waiver metadata
   */
  public function descriptor(): ApprovalActionDescriptor
  {
    return new ApprovalActionDescriptor(ApprovalActionTypes::NC_WAIVER, 'Non-conformity waiver', 'critical');
  }
  // #endregion
}
