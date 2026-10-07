<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Provider\EquipmentTypeCatalog;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Equipment\Application\UseCase\Query\EquipmentTypeCatalog\ListEquipmentTypes\{ListEquipmentTypesQuery, ListEquipmentTypesResult};
use Equipment\Presentation\Api\Dto\Output\EquipmentTypeCatalog\EquipmentTypeOutput;
use Equipment\Presentation\Api\Service\EquipmentTypeCatalogAccess;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function is_string;

/**
 * Provider EquipmentTypeCatalogProvider.
 *
 * Projects organization catalogs, including archived descriptors for historical display.
 *
 * @category Provider
 *
 * @implements ProviderInterface<EquipmentTypeOutput>
 */
final readonly class EquipmentTypeCatalogProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param QueryBusPort $queryBus catalog read dispatch
   * @param EquipmentTypeCatalogAccess $access organization gate
   *
   * @return void
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private EquipmentTypeCatalogAccess $access,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @access public
   *
   * @param Operation $operation current operation
   * @param array<string, mixed> $uriVariables scope and optional code
   * @param array<string, mixed> $context provider context
   *
   * @return list<EquipmentTypeOutput>|EquipmentTypeOutput catalog or item
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|EquipmentTypeOutput
  {
    $organizationId = $this->access->organization($uriVariables, 'organization.equipment.read');
    /** @var ListEquipmentTypesResult $result */
    $result = $this->queryBus->ask(new ListEquipmentTypesQuery($organizationId));
    $typeCode = $uriVariables['typeCode'] ?? null;
    $outputs = [];
    foreach ($result->types as $type) {
      $output = EquipmentTypeOutput::fromDescriptor($type);
      if (is_string($typeCode) && $type->value === $typeCode) {
        return $output;
      }
      $outputs[] = $output;
    }
    if (is_string($typeCode)) {
      throw new NotFoundHttpException('Equipment type not found.');
    }

    return $outputs;
  }
  // #endregion
}
