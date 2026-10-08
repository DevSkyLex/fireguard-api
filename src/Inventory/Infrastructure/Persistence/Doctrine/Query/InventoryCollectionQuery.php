<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Persistence\Doctrine\Query;

use LogicException;

use function in_array;
use function str_replace;

/**
 * Class InventoryCollectionQuery
 *
 * Builds the shared collection and count predicates without accessing a database.
 *
 * @category Query
 */
final readonly class InventoryCollectionQuery
{
  // #region Methods
  /**
   * Method table
   *
   * Selects a fixed Inventory table name from the supported collection types.
   *
   * @access public
   *
   * @param string $type the collection type
   *
   * @return string the trusted Inventory table name
   */
  public static function table(string $type): string
  {
    return match($type) {
      'parts' => 'inventory_parts','warehouses' => 'inventory_warehouses','balances' => 'inventory_balances','movements' => 'inventory_movements','consumptions' => 'inventory_declarations',default => throw new LogicException('Unknown inventory collection.')
    };
  }

  /**
   * Method selection
   *
   * Keeps list and count filters identical and bound to the owning organization.
   *
   * @access public
   *
   * @param string $type the collection type
   * @param string $org the owning organization
   * @param array<string,string> $filters the exact collection filters
   *
   * @return array{string,array<string,string>} SQL predicate and bound parameters
   */
  public static function selection(string $type, string $org, array $filters): array
  {
    $where = 'organization_id=:org';
    $params = ['org' => $org];
    if (in_array($type, ['parts', 'warehouses'], true)) {
      if (isset($filters['search'])) {
        $where .= " AND (code ILIKE :search ESCAPE '!' OR label ILIKE :search ESCAPE '!')";
        $params['search'] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']) . '%';
      }
      if (isset($filters['archived'])) {
        $where .= ' AND archived = CAST(:archived AS BOOLEAN)';
        $params['archived'] = $filters['archived'];
      }
    }
    foreach (['warehouseId' => 'warehouse_id', 'partId' => 'part_id', 'interventionId' => 'intervention_id', 'status' => 'status'] as $key => $column) {
      if (isset($filters[$key])) {
        $where .= ' AND ' . $column . '=:' . $key;
        $params[$key] = $filters[$key];
      }
    }

    return [$where, $params];
  }
  // #endregion
}
