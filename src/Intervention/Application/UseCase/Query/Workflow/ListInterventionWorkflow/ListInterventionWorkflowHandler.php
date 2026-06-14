<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow;

use Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListInterventionWorkflowHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionWorkflowHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListInterventionWorkflowHandler class.
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
   * @param ListInterventionWorkflowQuery $query the query value
   *
   * @return ListInterventionWorkflowResult the   invoke result
   */
  public function __invoke(ListInterventionWorkflowQuery $query): ListInterventionWorkflowResult
  {
    $organizationId = $query->scopeId;
    if ('intervention' !== $query->resource) {
      $context = $this->gateway->interventionContext($query->scopeId);
      if (null === $context) {
        throw InterventionNotFoundException::withId($query->scopeId);
      }
      $organizationId = $context->organizationId;
    }
    if (!$this->authorization->hasPermission($query->userId, $organizationId, 'organization.interventions.read')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    return new ListInterventionWorkflowResult($this->gateway->list(
      $query->resource,
      $query->scopeId,
      $query->filters,
      $query->page,
      $query->itemsPerPage,
    ));
  }
}
