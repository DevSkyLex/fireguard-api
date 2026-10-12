<?php

declare(strict_types=1);

namespace Onboarding\Infrastructure\Adapter\Idempotency;

use Onboarding\Application\Contract\Setup\OrganizationSetupOperation;
use Onboarding\Application\Contract\Step\OrganizationOnboardingStepDefinition;
use Onboarding\Application\Service\OrganizationOnboardingSessionState;
use Onboarding\Application\UseCase\Command\Flow\DismissOrganizationOnboarding\{DismissOrganizationOnboardingCommand, DismissOrganizationOnboardingResult};
use Onboarding\Application\UseCase\Command\Flow\ExecuteOrganizationOnboardingStep\{ExecuteOrganizationOnboardingStepCommand, ExecuteOrganizationOnboardingStepResult};
use Onboarding\Application\UseCase\Command\Flow\ResumeOrganizationOnboarding\{ResumeOrganizationOnboardingCommand, ResumeOrganizationOnboardingResult};
use Onboarding\Application\UseCase\Command\Flow\RollbackOrganizationOnboarding\{RollbackOrganizationOnboardingCommand, RollbackOrganizationOnboardingResult};
use Onboarding\Application\UseCase\Command\Flow\SkipOrganizationOnboardingStep\{SkipOrganizationOnboardingStepCommand, SkipOrganizationOnboardingStepResult};
use Onboarding\Application\UseCase\Command\Flow\StartOrganizationOnboarding\{StartOrganizationOnboardingCommand, StartOrganizationOnboardingResult};
use Onboarding\Application\UseCase\Command\Flow\SynchronizeOrganizationOnboarding\{SynchronizeOrganizationOnboardingCommand, SynchronizeOrganizationOnboardingResult};
use Onboarding\Application\UseCase\Command\Setup\PrepareOrganizationSetup\{PrepareOrganizationSetupCommand, PrepareOrganizationSetupResult};
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Declares every session mutation, including the stateful onboarding GET, on main.
 *
 * @category Adapter
 */
final readonly class OnboardingHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'getOrganizationOnboarding',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: SynchronizeOrganizationOnboardingCommand::class,
        resultClass: SynchronizeOrganizationOnboardingResult::class,
        contractClasses: [OrganizationOnboardingSessionState::class, OrganizationSetupOperation::class, OrganizationOnboardingStepDefinition::class],
        requiredKey: true,
        resultIdentifierProperty: 'organizationId',
        resourceKind: 'organization',
        resultIdentifierNullable: true,
      ),
      new HttpIdempotencyOperation(
        name: 'startOrganizationOnboarding',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: StartOrganizationOnboardingCommand::class,
        resultClass: StartOrganizationOnboardingResult::class,
        contractClasses: [OrganizationOnboardingSessionState::class, OrganizationSetupOperation::class, OrganizationOnboardingStepDefinition::class],
        requiredKey: true,
        resultIdentifierProperty: 'organizationId',
        resourceKind: 'organization',
        resultIdentifierNullable: true,
      ),
      new HttpIdempotencyOperation(
        name: 'executeOrganizationOnboardingStep',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: ExecuteOrganizationOnboardingStepCommand::class,
        resultClass: ExecuteOrganizationOnboardingStepResult::class,
        contractClasses: [OrganizationOnboardingSessionState::class, OrganizationSetupOperation::class, OrganizationOnboardingStepDefinition::class],
        requiredKey: true,
        resultIdentifierProperty: 'organizationId',
        resourceKind: 'organization',
        resultIdentifierNullable: true,
      ),
      new HttpIdempotencyOperation(
        name: 'skipOrganizationOnboardingStep',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: SkipOrganizationOnboardingStepCommand::class,
        resultClass: SkipOrganizationOnboardingStepResult::class,
        contractClasses: [OrganizationOnboardingSessionState::class, OrganizationSetupOperation::class, OrganizationOnboardingStepDefinition::class],
        requiredKey: true,
        resultIdentifierProperty: 'organizationId',
        resourceKind: 'organization',
        resultIdentifierNullable: true,
      ),
      new HttpIdempotencyOperation(
        name: 'rollbackOrganizationOnboarding',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: RollbackOrganizationOnboardingCommand::class,
        resultClass: RollbackOrganizationOnboardingResult::class,
        contractClasses: [OrganizationOnboardingSessionState::class, OrganizationSetupOperation::class, OrganizationOnboardingStepDefinition::class],
        requiredKey: true,
        resultIdentifierProperty: 'organizationId',
        resourceKind: 'organization',
        resultIdentifierNullable: true,
      ),
      new HttpIdempotencyOperation(
        name: 'dismissOrganizationOnboarding',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: DismissOrganizationOnboardingCommand::class,
        resultClass: DismissOrganizationOnboardingResult::class,
        contractClasses: [OrganizationOnboardingSessionState::class, OrganizationSetupOperation::class, OrganizationOnboardingStepDefinition::class],
        requiredKey: true,
        resultIdentifierProperty: 'organizationId',
        resourceKind: 'organization',
        resultIdentifierNullable: true,
      ),
      new HttpIdempotencyOperation(
        name: 'resumeOrganizationOnboarding',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: ResumeOrganizationOnboardingCommand::class,
        resultClass: ResumeOrganizationOnboardingResult::class,
        contractClasses: [OrganizationOnboardingSessionState::class, OrganizationSetupOperation::class, OrganizationOnboardingStepDefinition::class],
        requiredKey: true,
        resultIdentifierProperty: 'organizationId',
        resourceKind: 'organization',
        resultIdentifierNullable: true,
      ),
      new HttpIdempotencyOperation(
        name: 'prepareOrganizationSetup',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: PrepareOrganizationSetupCommand::class,
        resultClass: PrepareOrganizationSetupResult::class,
        contractClasses: [OrganizationOnboardingSessionState::class, OrganizationSetupOperation::class, OrganizationOnboardingStepDefinition::class],
        requiredKey: true,
        resultIdentifierProperty: 'organizationId',
        resourceKind: 'organization',
        resultIdentifierNullable: true,
      ),
    ];
  }
}
