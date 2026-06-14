<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Workflow\GetInterventionWorkflow;

use Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetInterventionWorkflowHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInterventionWorkflowHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetInterventionWorkflowHandler class.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowGatewayPort $gateway the gateway value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   */
  public function __construct(
    private InterventionWorkflowGatewayPort $gateway,
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
   * @param GetInterventionWorkflowQuery $query the query value
   *
   * @return GetInterventionWorkflowResult the   invoke result
   */
  public function __invoke(GetInterventionWorkflowQuery $query): GetInterventionWorkflowResult
  {
    $view = $this->gateway->get($query->resource, $query->id);
    if (null === $view) {
      throw InterventionNotFoundException::withId($query->id);
    }
    if (!$this->authorization->hasPermission($query->userId, $view->organizationId, 'organization.interventions.read')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    return new GetInterventionWorkflowResult($view);
  }
}
