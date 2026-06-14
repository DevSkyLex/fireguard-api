<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Workflow\ListInterventionIssues;

use Intervention\Application\Port\Outbound\{InterventionIssueQueryPort, InterventionWorkflowGatewayPort};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListInterventionIssuesHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionIssuesHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListInterventionIssuesHandler class.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowGatewayPort $gateway the gateway value
   * @param InterventionIssueQueryPort $issues the intervention issue query value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   */
  public function __construct(
    private InterventionWorkflowGatewayPort $gateway,
    private InterventionIssueQueryPort $issues,
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
   * @param ListInterventionIssuesQuery $query the query value
   *
   * @return ListInterventionIssuesResult the   invoke result
   */
  public function __invoke(ListInterventionIssuesQuery $query): ListInterventionIssuesResult
  {
    $context = $this->gateway->interventionContext($query->interventionId);
    if (null === $context) {
      throw InterventionNotFoundException::withId($query->interventionId);
    }
    if (!$this->authorization->hasPermission($query->userId, $context->organizationId, 'organization.interventions.read')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    return new ListInterventionIssuesResult($this->issues->issues($query->interventionId));
  }
}
