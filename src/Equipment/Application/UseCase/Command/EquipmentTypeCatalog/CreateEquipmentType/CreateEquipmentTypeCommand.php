<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\EquipmentTypeCatalog\CreateEquipmentType;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateEquipmentTypeCommand.
 *
 * @category UseCase
 */
final readonly class CreateEquipmentTypeCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $value stable type code
   * @param string $label display label
   * @param string $family catalog family
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $value,
    public string $label,
    public string $family,
  ) {
  }
  // #endregion
}
