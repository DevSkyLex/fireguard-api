<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListEquipments;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListEquipmentsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListEquipmentsQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public ?string $facilityId = null,
    public ?string $type = null,
    public ?string $status = null,
  ) {
  }
  // #endregion
}
