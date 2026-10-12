<?php

declare(strict_types=1);

namespace Billing\Application\Port\Outbound;

/**
 * Interface BillingReturnUrlPort
 *
 * Builds hosted-payment return links from server configuration; callers supply identifiers only.
 * These links describe navigation and never confirm payment or change entitlements.
 *
 * @category Port
 */
interface BillingReturnUrlPort
{
  // #region Methods
  /**
   * Method checkoutSuccess
   *
   * @access public
   *
   * @param string $organizationId the owning organization identifier
   * @param string $planKey the selected payable plan key
   * @param string $interval the selected validated billing cadence
   *
   * @return string the success navigation URL
   */
  public function checkoutSuccess(string $organizationId, string $planKey, string $interval): string;

  /**
   * Method checkoutCancel
   *
   * @access public
   *
   * @param string $organizationId the owning organization identifier
   *
   * @return string the cancelled Checkout navigation URL
   */
  public function checkoutCancel(string $organizationId): string;

  /**
   * Method portal
   *
   * @access public
   *
   * @param string $organizationId the owning organization identifier
   *
   * @return string the Billing Portal navigation URL
   */
  public function portal(string $organizationId): string;
  // #endregion
}
