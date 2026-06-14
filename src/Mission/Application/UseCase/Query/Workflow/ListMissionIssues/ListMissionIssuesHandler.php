<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Query\Workflow\ListMissionIssues;

use Mission\Application\Port\Outbound\{MissionIssueQueryPort, MissionWorkflowGatewayPort};
use Mission\Domain\Exception\{MissionAccessDeniedException, MissionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListMissionIssuesHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMissionIssuesHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListMissionIssuesHandler class.
   *
   * @since 1.0.0
   *
   * @param MissionWorkflowGatewayPort $gateway the gateway value
   * @param MissionIssueQueryPort $issues the mission issue query value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   */
  public function __construct(
    private MissionWorkflowGatewayPort $gateway,
    private MissionIssueQueryPort $issues,
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
   * @param ListMissionIssuesQuery $query the query value
   *
   * @return ListMissionIssuesResult the   invoke result
   */
  public function __invoke(ListMissionIssuesQuery $query): ListMissionIssuesResult
  {
    $context = $this->gateway->missionContext($query->missionId);
    if (null === $context) {
      throw MissionNotFoundException::withId($query->missionId);
    }
    if (!$this->authorization->hasPermission($query->userId, $context->organizationId, 'organization.missions.read')) {
      throw new MissionAccessDeniedException('Missing organization.missions.read permission.');
    }

    return new ListMissionIssuesResult($this->issues->issues($query->missionId));
  }
}
