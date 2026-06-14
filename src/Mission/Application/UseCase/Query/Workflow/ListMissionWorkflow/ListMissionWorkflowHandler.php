<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Query\Workflow\ListMissionWorkflow;

use Mission\Application\Port\Outbound\MissionWorkflowGatewayPort;
use Mission\Domain\Exception\{MissionAccessDeniedException, MissionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListMissionWorkflowHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMissionWorkflowHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListMissionWorkflowHandler class.
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
   * @param ListMissionWorkflowQuery $query the query value
   *
   * @return ListMissionWorkflowResult the   invoke result
   */
  public function __invoke(ListMissionWorkflowQuery $query): ListMissionWorkflowResult
  {
    $organizationId = $query->scopeId;
    if ('mission' !== $query->resource) {
      $context = $this->gateway->missionContext($query->scopeId);
      if (null === $context) {
        throw MissionNotFoundException::withId($query->scopeId);
      }
      $organizationId = $context->organizationId;
    }
    if (!$this->authorization->hasPermission($query->userId, $organizationId, 'organization.missions.read')) {
      throw new MissionAccessDeniedException('Missing organization.missions.read permission.');
    }

    return new ListMissionWorkflowResult($this->gateway->list(
      $query->resource,
      $query->scopeId,
      $query->filters,
      $query->page,
      $query->itemsPerPage,
    ));
  }
}
