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
    // For the `intervention` resource the scope id IS the organization id the
    // caller supplied; for every other resource it is an intervention id the
    // organization is resolved from. The two cases therefore need different
    // not-found messages below, though both must answer 404.
    $scopeIsOrganization = 'intervention' === $query->resource;
    $organizationId = $query->scopeId;
    if (!$scopeIsOrganization) {
      $context = $this->gateway->interventionContext($query->scopeId);
      if (null === $context) {
        throw InterventionNotFoundException::withId($query->scopeId);
      }
      $organizationId = $context->organizationId;
    }

    $decision = $this->authorization->resolveAccess($query->userId, $organizationId, 'organization.interventions.read');
    if ($decision->isOutsideScope()) {
      throw $scopeIsOrganization
        ? InterventionNotFoundException::forOrganizationScope($query->scopeId)
        : InterventionNotFoundException::withId($query->scopeId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    return new ListInterventionWorkflowResult($this->gateway->list(
      $query->resource,
      $query->scopeId,
      $query->filters,
      $query->page,
      $query->itemsPerPage,
      $query->sorting,
    ));
  }
}
