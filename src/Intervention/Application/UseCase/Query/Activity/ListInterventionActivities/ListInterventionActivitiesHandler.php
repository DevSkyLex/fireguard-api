<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Activity\ListInterventionActivities;

use Intervention\Application\Port\Outbound\{InterventionActivityPort, InterventionWorkflowGatewayPort};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListInterventionActivitiesHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionActivitiesHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListInterventionActivitiesHandler class.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowGatewayPort $gateway the gateway value
   * @param InterventionActivityPort $activities the activities value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   */
  public function __construct(
    private InterventionWorkflowGatewayPort $gateway,
    private InterventionActivityPort $activities,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }

  /**
   * Method __invoke.
   *
   * Executes the   invoke operation.
   *
   * @since 1.0.0
   *
   * @param ListInterventionActivitiesQuery $query the query value
   *
   * @return ListInterventionActivitiesResult the   invoke result
   */
  public function __invoke(ListInterventionActivitiesQuery $query): ListInterventionActivitiesResult
  {
    $context = $this->gateway->interventionContext($query->interventionId);
    if (null === $context) {
      throw InterventionNotFoundException::withId($query->interventionId);
    }
    if (!$this->authorization->hasPermission($query->userId, $context->organizationId, 'organization.interventions.read')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    return new ListInterventionActivitiesResult(
      $this->activities->listByIntervention($query->interventionId, $query->page, $query->itemsPerPage),
    );
  }
}
