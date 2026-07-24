<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipmentKpis;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetEquipmentKpisQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentKpisQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
  ) {
  }
  // #endregion
}
