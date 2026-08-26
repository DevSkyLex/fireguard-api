<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment;

use Equipment\Application\Contract\Equipment\CanonicalEquipmentView;
use Equipment\Application\Port\Outbound\CanonicalEquipmentRepositoryPort;
use Equipment\Domain\ValueObject\EquipmentId;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase GetCanonicalEquipmentHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCanonicalEquipmentHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CanonicalEquipmentRepositoryPort $equipment the canonical equipment repository
   */
  public function __construct(
    private CanonicalEquipmentRepositoryPort $equipment,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * A malformed identifier answers "not found" rather than "invalid": before
   * the identifier was a value object, `$entityManager->find()` simply
   * returned null for any unparseable string and the endpoint answered 404.
   *
   * @since 1.0.0
   *
   * @param GetCanonicalEquipmentQuery $query the query payload
   *
   * @return GetCanonicalEquipmentResult the use case result
   */
  public function __invoke(GetCanonicalEquipmentQuery $query): GetCanonicalEquipmentResult
  {
    try {
      $id = EquipmentId::fromString($query->equipmentId);
    } catch (InvalidValueException) {
      return new GetCanonicalEquipmentResult();
    }

    $equipment = $this->equipment->findById($id);

    if (null === $equipment) {
      return new GetCanonicalEquipmentResult();
    }

    return new GetCanonicalEquipmentResult(new CanonicalEquipmentView(
      id: (string) $equipment->id(),
      organizationId: (string) $equipment->organizationId(),
      recordStatus: $equipment->recordStatus()->value,
      interventionId: $equipment->interventionId(),
      revision: $equipment->revision(),
    ));
  }
  // #endregion
}
