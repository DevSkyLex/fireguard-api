<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListEquipmentTypes;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListEquipmentTypesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListEquipmentTypesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListEquipmentTypesQuery class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier (used for auth scoping only)
   */
  public function __construct(
    public string $organizationId,
  ) {
  }
  // #endregion
}
