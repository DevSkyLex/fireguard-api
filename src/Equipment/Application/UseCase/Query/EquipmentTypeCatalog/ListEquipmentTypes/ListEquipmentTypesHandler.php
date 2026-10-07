<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\EquipmentTypeCatalog\ListEquipmentTypes;

use Equipment\Application\Contract\EquipmentTypeCatalog\EquipmentTypeDescriptor;
use Equipment\Application\Port\Outbound\EquipmentTypeCatalogPort;
use Equipment\Domain\ValueObject\EquipmentOrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListEquipmentTypesHandler.
 *
 * @category UseCase
 */
final readonly class ListEquipmentTypesHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param EquipmentTypeCatalogPort $catalog catalog persistence
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
   * @param ListEquipmentTypesQuery $query scoped catalog request
   *
   * @return ListEquipmentTypesResult complete catalog
   */
  public function __invoke(ListEquipmentTypesQuery $query): ListEquipmentTypesResult
  {
    $organizationId = (string) EquipmentOrganizationId::fromString($query->organizationId);
    $types = [];
    foreach ($this->catalog->list($organizationId) as $type) {
      $types[] = new EquipmentTypeDescriptor($type->value, $type->label, $type->family, $type->archived, $type->revision);
    }

    return new ListEquipmentTypesResult($types);
  }
  // #endregion
}
