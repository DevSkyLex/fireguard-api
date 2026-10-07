<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Processor\EquipmentTypeCatalog;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Equipment\Application\UseCase\Command\EquipmentTypeCatalog\CreateEquipmentType\{CreateEquipmentTypeCommand, CreateEquipmentTypeResult};
use Equipment\Application\UseCase\Command\EquipmentTypeCatalog\PatchEquipmentType\{PatchEquipmentTypeCommand, PatchEquipmentTypeResult};
use Equipment\Presentation\Api\Dto\Input\EquipmentTypeCatalog\{CreateEquipmentTypeInput, PatchEquipmentTypeInput};
use Equipment\Presentation\Api\Dto\Output\EquipmentTypeCatalog\EquipmentTypeOutput;
use Equipment\Presentation\Api\Service\EquipmentTypeCatalogAccess;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function is_string;

/**
 * Processor EquipmentTypeCatalogProcessor.
 *
 * Translates authorized catalog writes to use cases; failure mapping is centralized.
 *
 * @category Processor
 *
 * @implements ProcessorInterface<CreateEquipmentTypeInput|PatchEquipmentTypeInput, EquipmentTypeOutput>
 */
final readonly class EquipmentTypeCatalogProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param CommandBusPort $commandBus catalog write dispatch
   * @param EquipmentTypeCatalogAccess $access organization gate
   *
   * @return void
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private EquipmentTypeCatalogAccess $access,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @access public
   *
   * @param mixed $data validated catalog input
   * @param Operation $operation current operation
   * @param array<string, mixed> $uriVariables organization and optional code
   * @param array<string, mixed> $context processor context
   *
   * @return EquipmentTypeOutput saved descriptor
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EquipmentTypeOutput
  {
    $organizationId = $this->access->organization($uriVariables, 'organization.equipment.write');
    if ($data instanceof CreateEquipmentTypeInput) {
      /** @var CreateEquipmentTypeResult $result */
      $result = $this->commandBus->dispatch(new CreateEquipmentTypeCommand($organizationId, $data->value, $data->label, $data->family));
    } elseif ($data instanceof PatchEquipmentTypeInput && is_string($uriVariables['typeCode'] ?? null)) {
      /** @var PatchEquipmentTypeResult $result */
      $result = $this->commandBus->dispatch(new PatchEquipmentTypeCommand(
        $organizationId,
        $uriVariables['typeCode'],
        $data->revision,
        $data->label,
        $data->family,
        $data->archived,
      ));
    } else {
      throw new BadRequestHttpException('Invalid equipment type input.');
    }

    return EquipmentTypeOutput::fromDescriptor($result->type);
  }
  // #endregion
}
