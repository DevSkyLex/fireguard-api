<?php

declare(strict_types=1);

namespace Approval\Application\Port\Outbound;

use Approval\Application\Contract\Action\ApprovalActionDescriptor;

/**
 * Interface ApprovalActionDescriptorProviderPort
 *
 * Owner modules contribute action metadata through the approval.action_descriptor tag.
 *
 * @category Port
 */
interface ApprovalActionDescriptorProviderPort
{
  // #region Methods
  /**
   * Method descriptor
   *
   * @access public
   *
   * @return ApprovalActionDescriptor the contributor's stable action metadata
   */
  public function descriptor(): ApprovalActionDescriptor;
  // #endregion
}
