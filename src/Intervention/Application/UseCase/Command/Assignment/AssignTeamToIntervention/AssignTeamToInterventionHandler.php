<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Assignment\AssignTeamToIntervention;

use Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort;
use Intervention\Application\UseCase\Command\Workflow\MutateInterventionWorkflow\{MutateInterventionWorkflowCommand, MutateInterventionWorkflowResult};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException, InterventionValidationException};
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, TeamDirectoryPort};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\CommandBusPort;

use function array_unique;
use function array_values;

/**
 * UseCase AssignTeamToInterventionHandler.
 *
 * Snapshot-expands an organization team's CURRENT active members into an
 * intervention's `participants` list. No change to the Intervention
 * aggregate, record, offline PUT/ETag, or revision engine: this dispatches
 * the EXISTING {@see MutateInterventionWorkflowCommand} so numbering,
 * activities, the planning freeze, and ETag/revision bumps all apply
 * identically to a manual participants edit. Expansion happens at
 * assignment time (a copy of member ids), never a live/dynamic link: a
 * later team-membership change never mutates an already-assigned
 * intervention, which keeps behavior deterministic under the offline/ETag
 * optimistic-concurrency replay model and the draft-only planning freeze.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssignTeamToInterventionHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AssignTeamToInterventionHandler class.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowGatewayPort $gateway the intervention workflow gateway
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param TeamDirectoryPort $teamDirectory the organization team directory port
   * @param CommandBusPort $commandBus the command bus
   */
  public function __construct(
    private InterventionWorkflowGatewayPort $gateway,
    private OrganizationAuthorizationPort $authorization,
    private TeamDirectoryPort $teamDirectory,
    private CommandBusPort $commandBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param AssignTeamToInterventionCommand $command the command payload
   */
  public function __invoke(AssignTeamToInterventionCommand $command): AssignTeamToInterventionResult
  {
    $context = $this->gateway->interventionContext($command->interventionId);
    if (null === $context) {
      throw InterventionNotFoundException::withId($command->interventionId);
    }

    if (!$this->authorization->hasPermission($command->userId, $context->organizationId, 'organization.interventions.plan')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.plan permission.');
    }

    $activeMemberIds = $this->teamDirectory->listActiveMemberIds($context->organizationId, $command->teamId);

    if ([] === $activeMemberIds) {
      throw new InterventionValidationException('The team has no active members to assign.');
    }

    $participants = array_values(array_unique([...$context->participants, ...$activeMemberIds]));

    /** @var MutateInterventionWorkflowResult $result */
    $result = $this->commandBus->dispatch(new MutateInterventionWorkflowCommand(
      resource: 'intervention',
      action: 'update',
      userId: $command->userId,
      id: $command->interventionId,
      payload: ['participants' => $participants],
      expectedRevision: null,
      createOnly: false,
    ));

    return new AssignTeamToInterventionResult($result->view);
  }
  // #endregion
}
