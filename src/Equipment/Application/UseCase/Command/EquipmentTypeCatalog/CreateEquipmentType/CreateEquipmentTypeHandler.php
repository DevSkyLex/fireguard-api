<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\EquipmentTypeCatalog\CreateEquipmentType;

use Equipment\Application\Contract\EquipmentTypeCatalog\EquipmentTypeDescriptor;
use Equipment\Application\Port\Outbound\EquipmentTypeCatalogPort;
use Equipment\Domain\Model\EquipmentTypeCatalog\EquipmentTypeDefinition;
use Equipment\Domain\ValueObject\EquipmentOrganizationId;
use Shared\Application\Message\CommandHandler;

/**
 * UseCase CreateEquipmentTypeHandler.
 *
 * @category UseCase
 */
final readonly class CreateEquipmentTypeHandler implements CommandHandler
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
   * @param CreateEquipmentTypeCommand $command requested catalog mutation
   *
   * @return CreateEquipmentTypeResult stored descriptor
   */
  public function __invoke(CreateEquipmentTypeCommand $command): CreateEquipmentTypeResult
  {
    $organizationId = (string) EquipmentOrganizationId::fromString($command->organizationId);
    $type = EquipmentTypeDefinition::create($command->value, $command->label, $command->family);
    $this->catalog->save($organizationId, $type, null);

    return new CreateEquipmentTypeResult(new EquipmentTypeDescriptor($type->value, $type->label, $type->family, $type->archived, $type->revision));
  }
  // #endregion
}
