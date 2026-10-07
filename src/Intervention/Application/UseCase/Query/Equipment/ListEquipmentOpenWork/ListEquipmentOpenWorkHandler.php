<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Equipment\ListEquipmentOpenWork;

use Intervention\Application\Port\Outbound\{InterventionEquipmentWorkPort, InterventionResourceGatewayPort};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Intervention\Domain\ValueObject\InterventionResourceType;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * Class ListEquipmentOpenWorkHandler
 *
 * Authorizes both the requested organization and the equipment before querying its work.
 *
 * @category UseCase
 */
final readonly class ListEquipmentOpenWorkHandler implements QueryHandler
{
  /**
   * Method __construct
   *
   * @access public
   *
   * @param OrganizationAuthorizationPort $authorization organization permission checks
   * @param InterventionResourceGatewayPort $resources owner-published equipment scope checks
   * @param InterventionEquipmentWorkPort $work scoped work projection
   *
   * @return void
   */
  public function __construct(private OrganizationAuthorizationPort $authorization, private InterventionResourceGatewayPort $resources, private InterventionEquipmentWorkPort $work)
  {
  }

  /**
   * Method __invoke
   *
   * @access public
   *
   * @param ListEquipmentOpenWorkQuery $query caller and requested equipment scope
   *
   * @return ListEquipmentOpenWorkResult authorized existing work
   */
  public function __invoke(ListEquipmentOpenWorkQuery $query): ListEquipmentOpenWorkResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.interventions.read');
    if ($decision->isOutsideScope()) {
      throw InterventionNotFoundException::withId($query->equipmentId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }
    if (!$this->resources->resourceBelongsToOrganization(InterventionResourceType::EQUIPMENT, $query->equipmentId, $query->organizationId)) {
      throw InterventionNotFoundException::withId($query->equipmentId);
    }

    return new ListEquipmentOpenWorkResult($this->work->findOpenWork($query->organizationId, $query->equipmentId));
  }
}
