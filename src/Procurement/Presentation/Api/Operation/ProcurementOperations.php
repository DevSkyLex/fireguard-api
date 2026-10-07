<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\Operation;

/** Stable organization-scoped procurement operation names. */
final class ProcurementOperations
{
  public const string SUPPLIERS = 'procurement_suppliers';

  public const string SUPPLIER = 'procurement_supplier';

  public const string CREATE_SUPPLIER = 'procurement_create_supplier';

  public const string CHANGE_SUPPLIER = 'procurement_change_supplier';

  public const string ARCHIVE_SUPPLIER = 'procurement_archive_supplier';

  public const string ORDERS = 'procurement_orders';

  public const string ORDER = 'procurement_order';

  public const string CREATE_ORDER = 'procurement_create_order';

  public const string CHANGE_ORDER = 'procurement_change_order';

  public const string PLACE_ORDER = 'procurement_place_order';

  public const string CANCEL_REMAINING = 'procurement_cancel_remaining';

  public const string RECEIPTS = 'procurement_receipts';

  public const string RECEIPT = 'procurement_receipt';

  public const string RECEIVE = 'procurement_receive';

  public const string INDIVIDUALIZE = 'procurement_individualize';

  public const string RETURN = 'procurement_return';

  public const string RETURNS = 'procurement_returns';

  public const string RETURN_DETAIL = 'procurement_return_detail';

  public const string RECONCILE_RETURN = 'procurement_reconcile_return';
}
