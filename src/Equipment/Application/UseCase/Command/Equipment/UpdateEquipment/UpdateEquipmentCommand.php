<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\UpdateEquipment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateEquipmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateEquipmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the equipment fields submitted for an organization-scoped update.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to validate the equipment
   * @param string $equipmentId equipment to update
   * @param string $type new equipment type
   * @param ?string $subType optional subtype value
   * @param ?string $brand optional manufacturer or brand value
   * @param ?string $model optional model value
   * @param ?string $serialNumber optional serial number
   * @param ?string $locationLabel optional location description
   *
   * @return void
   */
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
