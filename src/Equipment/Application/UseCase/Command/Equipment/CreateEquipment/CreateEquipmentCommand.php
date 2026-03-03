<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\CreateEquipment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateEquipmentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateEquipmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $type the equipment type
   * @param ?string $subType the optional sub-type
   * @param ?string $brand the optional brand
   * @param ?string $model the optional model
   * @param ?string $serialNumber the optional serial number
   * @param ?string $locationLabel the optional location label
   */
  public function __construct(
    public string $organizationId,
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
