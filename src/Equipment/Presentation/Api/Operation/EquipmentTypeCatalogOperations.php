<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Operation;

/**
 * Class EquipmentTypeCatalogOperations.
 *
 * Names organization-owned catalog operations independently of equipment mutations.
 *
 * @category Operation
 */
final class EquipmentTypeCatalogOperations
{
  // #region Constants
  /**
   * Constant LIST.
   */
  public const string LIST = 'equipment_type_catalog_list';

  /**
   * Constant GET.
   */
  public const string GET = 'equipment_type_catalog_get';

  /**
   * Constant CREATE.
   */
  public const string CREATE = 'equipment_type_catalog_create';

  /**
   * Constant PATCH.
   */
  public const string PATCH = 'equipment_type_catalog_patch';
  // #endregion
}
