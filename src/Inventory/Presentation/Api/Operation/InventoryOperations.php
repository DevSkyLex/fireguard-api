<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Operation;

/** @category Operation */
final class InventoryOperations
{
  public const string PARTS_LIST = 'inventory_parts_list';

  public const string PARTS_GET = 'inventory_parts_get';

  public const string PARTS_CREATE = 'inventory_parts_create';

  public const string PARTS_PATCH = 'inventory_parts_patch';

  public const string WAREHOUSES_LIST = 'inventory_warehouses_list';

  public const string WAREHOUSES_GET = 'inventory_warehouses_get';

  public const string WAREHOUSES_CREATE = 'inventory_warehouses_create';

  public const string WAREHOUSES_PATCH = 'inventory_warehouses_patch';

  public const string BALANCES_LIST = 'inventory_balances_list';

  public const string CONSUMPTIONS_LIST = 'inventory_consumptions_list';

  public const string CONSUMPTIONS_GET = 'inventory_consumptions_get';

  public const string CONSUMPTIONS_CREATE = 'inventory_consumptions_create';

  public const string CONSUMPTIONS_RECONCILE = 'inventory_consumptions_reconcile';

  public const string MOVEMENTS_LIST = 'inventory_movements_list';

  public const string MOVEMENTS_GET = 'inventory_movements_get';

  public const string RETURNS_CREATE = 'inventory_returns_create';

  public const string CORRECTIONS_CREATE = 'inventory_corrections_create';
}
