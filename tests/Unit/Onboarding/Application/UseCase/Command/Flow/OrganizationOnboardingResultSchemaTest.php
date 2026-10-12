<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Application\UseCase\Command\Flow;

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
use Onboarding\Presentation\Api\Mapper\Onboarding\OrganizationOnboardingOutputAssembler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Infrastructure\Idempotency\HttpIdempotencyResultCodec;

use function get_object_vars;

/**
 * Pins all HTTP outcomes to immutable snapshots, including historical open keys and setup inputs.
 *
 * @category UseCase Tests
 */
final class OrganizationOnboardingResultSchemaTest extends TestCase
{
  /**
   * @param class-string<CommandMessage> $commandClass exact primary message
   * @param class-string<ResultMessage> $resultClass exact immutable result
   */
  #[DataProvider('operations')]
  public function testCommittedStateCanBeReconstitutedWithoutInstalledHistoricalContributors(string $commandClass, string $resultClass): void
  {
    $state = new OrganizationOnboardingSessionState(
      flow: 'organization',
      state: 'in_progress',
      nextStep: 'current_external_step',
      blockedReason: null,
      targetOrganizationId: 'organization-1',
      targetOrganizationName: 'Historical workspace',
      completedSteps: ['removed_owner_step'],
      skippedSteps: ['retired_optional_step'],
      stepHistory: [
        ['stepKey' => 'removed_owner_step', 'occurredAt' => '2026-06-01T10:00:00+00:00', 'skipped' => false],
        ['stepKey' => 'retired_optional_step', 'occurredAt' => '2026-06-02T10:00:00+00:00', 'skipped' => true],
      ],
      updatedAt: '2026-10-11T12:00:00+00:00',
      canRollback: false,
      lastRollbackableStep: null,
      dismissed: true,
      dismissedAt: '2026-10-11T12:00:00+00:00',
      accessibleOrganizationId: 'joined-workspace',
      sessionId: 'session-1',
      setupOperations: [
        new OrganizationSetupOperation('removed_owner_step', 'item-b', ['name' => 'Second', 'nullable' => null], 'resource-b', ['id' => 'resource-b']),
        new OrganizationSetupOperation('current_external_step', 'item-a', ['name' => 'First', 'values' => [2, 1]], null),
      ],
      stepDefinitions: [new OrganizationOnboardingStepDefinition('current_external_step', 'External current step', 1, true, 'POST', '/external')],
    );
    $operation = new HttpIdempotencyOperation(
      'onboarding_test',
      'main',
      'bus_owner',
      $commandClass,
      $resultClass,
      [OrganizationOnboardingSessionState::class, OrganizationSetupOperation::class, OrganizationOnboardingStepDefinition::class],
    );
    $result = new $resultClass($state);
    $codec = new HttpIdempotencyResultCodec();
    $decoded = $codec->decode($codec->encode($result, $operation), $operation);
    self::assertEquals($result, $decoded);
    self::assertSame('organization-1', get_object_vars($decoded)['organizationId']);
    $replayedState = get_object_vars($decoded)['state'];
    self::assertInstanceOf(OrganizationOnboardingSessionState::class, $replayedState);
    self::assertEquals(OrganizationOnboardingOutputAssembler::fromState($state), OrganizationOnboardingOutputAssembler::fromState($replayedState));
    self::assertSame(['removed_owner_step'], $replayedState->completedSteps);
    self::assertSame(['retired_optional_step'], $replayedState->skippedSteps);
    self::assertSame(['item-b', 'item-a'], [$replayedState->setupOperations[0]->itemKey, $replayedState->setupOperations[1]->itemKey]);
    self::assertSame([2, 1], $replayedState->setupOperations[1]->payload['values']);
    self::assertArrayHasKey('nullable', $replayedState->setupOperations[0]->payload);
    self::assertNull($replayedState->setupOperations[0]->payload['nullable']);
  }

  /**
   * @return iterable<string, array{class-string<CommandMessage>, class-string<ResultMessage>}>
   */
  public static function operations(): iterable
  {
    yield 'SynchronizeOrganizationOnboarding' => [SynchronizeOrganizationOnboardingCommand::class, SynchronizeOrganizationOnboardingResult::class];
    yield 'StartOrganizationOnboarding' => [StartOrganizationOnboardingCommand::class, StartOrganizationOnboardingResult::class];
    yield 'ExecuteOrganizationOnboardingStep' => [ExecuteOrganizationOnboardingStepCommand::class, ExecuteOrganizationOnboardingStepResult::class];
    yield 'SkipOrganizationOnboardingStep' => [SkipOrganizationOnboardingStepCommand::class, SkipOrganizationOnboardingStepResult::class];
    yield 'RollbackOrganizationOnboarding' => [RollbackOrganizationOnboardingCommand::class, RollbackOrganizationOnboardingResult::class];
    yield 'DismissOrganizationOnboarding' => [DismissOrganizationOnboardingCommand::class, DismissOrganizationOnboardingResult::class];
    yield 'ResumeOrganizationOnboarding' => [ResumeOrganizationOnboardingCommand::class, ResumeOrganizationOnboardingResult::class];
    yield 'PrepareOrganizationSetup' => [PrepareOrganizationSetupCommand::class, PrepareOrganizationSetupResult::class];
  }
}
