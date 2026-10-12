<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Idempotency;

use LogicException;
use Organization\Application\UseCase\Command\Organization\ChangeSelfServiceOrganizationPlan\ChangeSelfServiceOrganizationPlanCommand;
use Organization\Application\UseCase\Command\Organization\RestoreOrganization\RestoreOrganizationCommand;
use Organization\Application\UseCase\Command\Organization\SuspendOrganization\SuspendOrganizationCommand;
use Organization\Application\UseCase\Command\Organization\TransferOrganizationOwnership\TransferOrganizationOwnershipCommand;
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings\UpdateOrganizationSettingsCommand;
use Organization\Application\UseCase\Command\Organization\UploadOrganizationLogo\UploadOrganizationLogoCommand;
use Organization\Application\UseCase\Command\Plan\CreatePlan\{CreatePlanCommand, CreatePlanResult};
use Organization\Application\UseCase\Command\Plan\UpdatePlan\UpdatePlanCommand;
use Organization\Application\UseCase\Command\Team\UpdateTeam\UpdateTeamCommand;
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationQuery;
use Organization\Application\UseCase\Query\Plan\GetPlan\GetPlanQuery;
use Organization\Application\UseCase\Query\Team\GetTeam\GetTeamQuery;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\CurrentActorPort;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyResponseProjectionPort;

use function in_array;

/**
 * Class OrganizationHttpIdempotencyResponseProjectionAdapter
 *
 * Declares the exact output reads processors perform after mutation so their original projection is captured before the same final commit.
 *
 * @category Adapter
 */
final readonly class OrganizationHttpIdempotencyResponseProjectionAdapter implements HttpIdempotencyResponseProjectionPort
{
  // #region Constructor
  /**
   * @param CurrentActorPort $actor current original caller for membership-dependent output fields
   */
  public function __construct(private CurrentActorPort $actor)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operation): bool
  {
    return in_array($operation, ['updateOrganizationSettings', 'changeOrganizationPlan', 'uploadOrganizationLogo', 'transferOrganizationOwnership', 'suspendOrganization', 'restoreOrganization', 'createPlan', 'updatePlan', 'updateTeam'], true);
  }

  /**
   * {@inheritdoc}
   */
  public function queries(string $operation, CommandMessage $command, ResultMessage $result): array
  {
    if (!$this->supports($operation)) {
      throw new LogicException('Organization response projection operation is not declared.');
    }
    if ($command instanceof CreatePlanCommand && $result instanceof CreatePlanResult) {
      return [new GetPlanQuery($result->planId)];
    }
    if ($command instanceof UpdatePlanCommand) {
      return [new GetPlanQuery($command->planId)];
    }
    if ($command instanceof UpdateTeamCommand) {
      return [new GetTeamQuery($command->organizationId, $command->teamId)];
    }
    if ($command instanceof UpdateOrganizationSettingsCommand) {
      return [new GetOrganizationQuery($command->organizationId, $this->actor->userId())];
    }
    if ($command instanceof SuspendOrganizationCommand || $command instanceof RestoreOrganizationCommand || $command instanceof TransferOrganizationOwnershipCommand) {
      return [new GetOrganizationQuery($command->organizationId, $command->actingUserId)];
    }
    if ($command instanceof ChangeSelfServiceOrganizationPlanCommand || $command instanceof UploadOrganizationLogoCommand) {
      return [new GetOrganizationQuery($command->organizationId)];
    }

    throw new LogicException('Organization response projection command is not declared.');
  }
  // #endregion
}
