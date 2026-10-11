<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail;

use Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment\{GetCanonicalEquipmentQuery, GetCanonicalEquipmentResult};
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\{GetEquipmentQuery, GetEquipmentResult};
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentMediaNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Inbound\QueryBusPort;

/** Resolves contextual ownership before complete equipment enrichment. @category UseCase @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class GetEquipmentDetailHandler implements QueryHandler
{
  /**
   * @since 1.0.0
   */
  public function __construct(private QueryBusPort $queries, private OrganizationAuthorizationPort $authorization)
  {
  }

  /**
   * @since 1.0.0
   */
  public function __invoke(GetEquipmentDetailQuery $query): GetEquipmentDetailResult
  {
    /** @var GetCanonicalEquipmentResult $canonical */
    $canonical = $this->queries->ask(new GetCanonicalEquipmentQuery($query->equipmentId));
    $view = $canonical->view;
    if (null === $view || (null !== $query->organizationId && $view->organizationId !== $query->organizationId)) {
      throw new EquipmentMediaNotFoundException('Equipment not found.');
    }
    if (null !== $query->userId) {
      $decision = $this->authorization->resolveAccess($query->userId, $view->organizationId, 'organization.equipment.read');
      if ($decision->isOutsideScope()) {
        throw new EquipmentMediaNotFoundException('Equipment not found.');
      }
      if (!$decision->isGranted()) {
        throw new EquipmentAccessDeniedException('Missing organization.equipment.read permission.');
      }
    }
    /** @var GetEquipmentResult $equipment */
    $equipment = $this->queries->ask(new GetEquipmentQuery($view->organizationId, $query->equipmentId));

    return new GetEquipmentDetailResult($equipment, $view);
  }
}
