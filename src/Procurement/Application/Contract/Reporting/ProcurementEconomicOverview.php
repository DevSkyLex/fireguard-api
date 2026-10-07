<?php

declare(strict_types=1);

namespace Procurement\Application\Contract\Reporting;

/**
 * Class ProcurementEconomicOverview
 *
 * Reports active commitments and physical receipts for an order-creation window without supplier identities.
 *
 * @category Contract
 */
final readonly class ProcurementEconomicOverview
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $currency single configured organization currency
   * @param string $from inclusive ISO instant for order creation
   * @param string $to exclusive ISO instant for order creation
   * @param int $orderCount selected non-draft orders, including cancelled orders with retained deliveries
   * @param int $receiptCount physical receipts on selected orders across their entire history
   * @param int $pendingIndividualizationCount equipment receipts still awaiting individualization
   * @param ProcurementEconomicAmount $ordered effective committed purchase value excluding cancelled remainder
   * @param ProcurementEconomicAmount $received gross physical delivery value before returns
   * @param ProcurementEconomicAmount $outstanding remaining receivable quantity value, never reopened by returns
   * @param ProcurementEconomicAmount $returned physically declared supplier return value, including pending stock reconciliation
   * @param string $basis order_created_at identifies the selection basis
   * @param string $receiptScope selected_orders_all_history identifies the receipt scope
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $currency,
    public string $from,
    public string $to,
    public int $orderCount,
    public int $receiptCount,
    public int $pendingIndividualizationCount,
    public ProcurementEconomicAmount $ordered,
    public ProcurementEconomicAmount $received,
    public ProcurementEconomicAmount $outstanding,
    public ProcurementEconomicAmount $returned,
    public string $basis = 'order_created_at',
    public string $receiptScope = 'selected_orders_all_history',
  ) {
  }
  // #endregion
}
