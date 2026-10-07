<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\EquipmentTypeCatalog\PatchEquipmentType;

use Equipment\Application\Contract\EquipmentTypeCatalog\EquipmentTypeDescriptor;
use Equipment\Application\Port\Outbound\EquipmentTypeCatalogPort;
use Equipment\Domain\Exception\EquipmentTypeCatalogException;
use Equipment\Domain\ValueObject\EquipmentOrganizationId;
use Shared\Application\Message\CommandHandler;

/**
 * UseCase PatchEquipmentTypeHandler.
 *
 * @category UseCase
 */
final readonly class PatchEquipmentTypeHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param EquipmentTypeCatalogPort $catalog scoped catalog persistence
   *
   * @return void
   */
  public function __construct(private EquipmentTypeCatalogPort $catalog)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @access public
   *
   * @param PatchEquipmentTypeCommand $command requested catalog mutation
   *
   * @return PatchEquipmentTypeResult stored descriptor
   */
  public function __invoke(PatchEquipmentTypeCommand $command): PatchEquipmentTypeResult
  {
    $organizationId = (string) EquipmentOrganizationId::fromString($command->organizationId);
    $current = $this->catalog->find($organizationId, $command->value);
    if (null === $current) {
      throw new EquipmentTypeCatalogException('equipment_type_not_found', 'Equipment type not found.');
    }
    if ($current->revision !== $command->revision) {
      throw new EquipmentTypeCatalogException('equipment_type_revision_conflict', 'The equipment type changed. Reload before editing.');
    }
    $type = $current->revise($command->label, $command->family, $command->archived);
    $this->catalog->save($organizationId, $type, $command->revision);

    return new PatchEquipmentTypeResult(new EquipmentTypeDescriptor($type->value, $type->label, $type->family, $type->archived, $type->revision));
  }
  // #endregion
}
