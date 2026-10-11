<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

/**
 * Exception PaidOrganizationPlanRequiresBillingException.
 *
 * @category Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PaidOrganizationPlanRequiresBillingException extends RuntimeException
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   */
  public function __construct()
  {
    parent::__construct('Paid plans must be subscribed to through billing. Use the checkout flow to upgrade.');
  }
  // #endregion
}
