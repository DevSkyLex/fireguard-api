<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\EquipmentTypeCatalog\PatchEquipmentType;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase PatchEquipmentTypeCommand.
 *
 * @category UseCase
 */
final readonly class PatchEquipmentTypeCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $value stable type code
   * @param int $revision observed catalog revision
   * @param string|null $label replacement label
   * @param string|null $family replacement family
   * @param bool|null $archived replacement archive state
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $value,
    public int $revision,
    public ?string $label = null,
    public ?string $family = null,
    public ?bool $archived = null,
  ) {
  }
  // #endregion
}
