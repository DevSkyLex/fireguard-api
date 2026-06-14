<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Query\Workflow\GetMissionWorkflow;

use Mission\Application\Port\Outbound\MissionWorkflowGatewayPort;
use Mission\Domain\Exception\{MissionAccessDeniedException, MissionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetMissionWorkflowHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetMissionWorkflowHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetMissionWorkflowHandler class.
   *
   * @since 1.0.0
   *
   * @param MissionWorkflowGatewayPort $gateway the gateway value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   */
  public function __construct(
    private MissionWorkflowGatewayPort $gateway,
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
   * @param GetMissionWorkflowQuery $query the query value
   *
   * @return GetMissionWorkflowResult the   invoke result
   */
  public function __invoke(GetMissionWorkflowQuery $query): GetMissionWorkflowResult
  {
    $view = $this->gateway->get($query->resource, $query->id);
    if (null === $view) {
      throw MissionNotFoundException::withId($query->id);
    }
    if (!$this->authorization->hasPermission($query->userId, $view->organizationId, 'organization.missions.read')) {
      throw new MissionAccessDeniedException('Missing organization.missions.read permission.');
    }

    return new GetMissionWorkflowResult($view);
  }
}
