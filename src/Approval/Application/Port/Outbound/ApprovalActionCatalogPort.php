<?php

declare(strict_types=1);

namespace Approval\Application\Port\Outbound;

use Approval\Application\Contract\Action\ApprovalActionDescriptor;

/**
 * Interface ApprovalActionCatalogPort
 *
 * Publishes installed action metadata without exposing executors or persistence.
 *
 * @category Port
 */
interface ApprovalActionCatalogPort
{
  // #region Methods
  /**
   * Method descriptors
   *
   * @access public
   *
   * @return list<ApprovalActionDescriptor> the registered actions in contribution order
   */
  public function descriptors(): array;

  /**
   * Method find
   *
   * @access public
   *
   * @param string $value the stable action identifier
   *
   * @return ?ApprovalActionDescriptor metadata or null when no contributor is installed
   */
  public function find(string $value): ?ApprovalActionDescriptor;
  // #endregion
}
