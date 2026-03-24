<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListEquipmentTypes;

use Equipment\Domain\ValueObject\EquipmentType;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListEquipmentTypesHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListEquipmentTypesHandler implements QueryHandler
{
  // #region Methods

  /**
   * Method __invoke.
   *
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param ListEquipmentTypesQuery $query the query payload
   */
  public function __invoke(ListEquipmentTypesQuery $query): ListEquipmentTypesResult
  {
    $results = [];

    foreach (EquipmentType::cases() as $type) {
      $results[] = new GetEquipmentTypeResult(
        value: $type->value,
        label: $type->label(),
      );
    }

    return new ListEquipmentTypesResult($results);
  }
  // #endregion
}
