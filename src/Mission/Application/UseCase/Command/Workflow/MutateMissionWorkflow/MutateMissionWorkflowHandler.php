<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Command\Workflow\MutateMissionWorkflow;

use Mission\Application\Contract\Workflow\{MissionWorkflowContext, MissionWorkflowMutation};
use Mission\Application\Port\Outbound\MissionWorkflowGatewayPort;
use Mission\Application\Service\MissionMemberPolicy;
use Mission\Domain\Exception\{MissionAccessDeniedException, MissionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;

use function is_string;

/**
 * UseCase MutateMissionWorkflowHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MutateMissionWorkflowHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MutateMissionWorkflowHandler class.
   *
   * @since 1.0.0
   *
   * @param MissionWorkflowGatewayPort $gateway the gateway value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param MissionMemberPolicy $memberPolicy the mission member policy
   */
  public function __construct(
    private MissionWorkflowGatewayPort $gateway,
    private OrganizationAuthorizationPort $authorization,
    private MissionMemberPolicy $memberPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * Executes the   invoke operation.
   *
   * @since 1.0.0
   *
   * @param MutateMissionWorkflowCommand $command the command value
   *
   * @return MutateMissionWorkflowResult the   invoke result
   */
  public function __invoke(MutateMissionWorkflowCommand $command): MutateMissionWorkflowResult
  {
    $context = $this->context($command);
    $permission = $this->permission($command, $context);
    if (!$this->authorization->hasPermission($command->userId, $context->organizationId, $permission)) {
      throw new MissionAccessDeniedException('Missing ' . $permission . ' permission.');
    }
    if ('organization.missions.execute' === $permission) {
      $this->memberPolicy->assertCanExecuteMission(
        $context->organizationId,
        $command->userId,
        $context->responsibleId,
        $context->participants,
      );
    }

    return new MutateMissionWorkflowResult($this->gateway->mutate(new MissionWorkflowMutation(
      resource: $command->resource,
      action: $command->action,
      userId: $command->userId,
      id: $command->id,
      payload: $command->payload,
      expectedRevision: $command->expectedRevision,
      createOnly: $command->createOnly,
    )));
  }

  /**
   * Method context.
   *
   * Executes the context operation.
   *
   * @since 1.0.0
   *
   * @param MutateMissionWorkflowCommand $command the command value
   *
   * @return MissionWorkflowContext the context result
   */
  private function context(MutateMissionWorkflowCommand $command): MissionWorkflowContext
  {
    if ('create' === $command->action && 'mission' === $command->resource) {
      $organizationId = $command->payload['organizationId'] ?? null;
      if (!is_string($organizationId) || '' === $organizationId) {
        throw MissionNotFoundException::withId('organization');
      }

      return new MissionWorkflowContext(
        missionId: $command->id ?? '',
        organizationId: $organizationId,
        status: 'draft',
        responsibleId: is_string($command->payload['responsibleId'] ?? null) ? $command->payload['responsibleId'] : null,
      );
    }

    if ('create' === $command->action) {
      $missionId = $command->payload['missionId'] ?? null;
      $context = is_string($missionId) ? $this->gateway->missionContext($missionId) : null;
    } else {
      $context = null === $command->id ? null : $this->gateway->resourceContext($command->resource, $command->id);
    }
    if (!$context instanceof MissionWorkflowContext) {
      throw MissionNotFoundException::withId($command->id ?? 'unknown');
    }

    return $context;
  }

  /**
   * Method permission.
   *
   * Executes the permission operation.
   *
   * @since 1.0.0
   *
   * @param MutateMissionWorkflowCommand $command the command value
   * @param MissionWorkflowContext $context the context value
   *
   * @return string the permission result
   */
  private function permission(MutateMissionWorkflowCommand $command, MissionWorkflowContext $context): string
  {
    if ('mission' === $command->resource) {
      if ('create' === $command->action) {
        return 'organization.missions.plan';
      }
      $status = $command->payload['status'] ?? null;
      if (is_string($status)) {
        return match ($status) {
          'planned' => 'organization.missions.plan',
          'in_progress', 'submitted' => 'organization.missions.execute',
          'abandoned' => match ($context->status) {
            'draft' => 'organization.missions.plan',
            'changes_requested' => 'organization.missions.review',
            default => 'organization.missions.execute',
          },
          'changes_requested' => 'organization.missions.review',
          default => 'organization.missions.plan',
        };
      }

      return 'draft' === $context->status ? 'organization.missions.plan' : 'organization.missions.execute';
    }

    if ('work_item' === $command->resource) {
      return 'draft' === $context->status ? 'organization.missions.plan' : 'organization.missions.execute';
    }

    return 'submitted' === $context->status && 'create' !== $command->action
      ? 'organization.missions.review'
      : 'organization.missions.execute';
  }
}
