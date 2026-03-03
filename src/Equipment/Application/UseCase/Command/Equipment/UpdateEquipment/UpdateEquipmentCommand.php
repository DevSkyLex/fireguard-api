<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\UpdateEquipment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateEquipmentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateEquipmentCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $type,
    public ?string $subType = null,
    public ?string $brand = null,
    public ?string $model = null,
    public ?string $serialNumber = null,
    public ?string $locationLabel = null,
  ) {
  }
  // #endregion
}
