<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Presentation\Api\Mapper\Onboarding;

use Onboarding\Application\Service\OrganizationOnboardingSessionState;
use Onboarding\Domain\ValueObject\{OrganizationOnboardingState, OrganizationOnboardingStep};
use Onboarding\Presentation\Api\Mapper\Onboarding\OrganizationOnboardingOutputAssembler;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationOnboardingOutputAssembler::class)]
final class OrganizationOnboardingOutputAssemblerTest extends TestCase
{
  #[Test]
  public function testFromStateWithNoOrganization(): void
  {
    $state = $this->buildState(
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::CREATE_ORGANIZATION,
      targetOrganizationId: null,
      targetOrganizationName: null,
      completedSteps: [],
      canRollback: false,
      lastRollbackableStep: null,
    );

    $output = OrganizationOnboardingOutputAssembler::fromState($state);

    self::assertSame('organization', $output->flow);
    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $output->state);
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $output->nextStep);
    self::assertNull($output->blockedReason);
    self::assertNull($output->targetOrganizationId);
    self::assertFalse($output->canRollback);
    self::assertNull($output->rollbackPath);
    self::assertNull($output->rollbackMethod);

    self::assertCount(2, $output->steps);

    $createStep = $output->steps[0];
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $createStep->key);
    self::assertSame('pending', $createStep->status);
    self::assertTrue($createStep->required);
    self::assertTrue($createStep->available);
    self::assertNull($createStep->reason);
    self::assertSame('POST', $createStep->actionMethod);
    self::assertSame('/api/organizations', $createStep->actionPath);
    self::assertFalse($createStep->rollbackAvailable);

    $inviteStep = $output->steps[1];
    self::assertSame(OrganizationOnboardingStep::INVITE_MEMBERS, $inviteStep->key);
    self::assertSame('blocked', $inviteStep->status);
    self::assertFalse($inviteStep->required);
    self::assertFalse($inviteStep->available);
    self::assertSame('organization_required', $inviteStep->reason);
    self::assertSame('/api/organizations/{organizationId}/invitations', $inviteStep->actionPath);
    self::assertTrue($inviteStep->skippable);
    self::assertFalse($inviteStep->rollbackAvailable);
  }

  #[Test]
  public function testFromStateWithOrgExistsButCreateNotYetConfirmed(): void
  {
    $orgId = '550e8400-e29b-41d4-a716-446655440005';

    // targetOrganizationId is set (org was created externally) but CREATE_ORGANIZATION
    // is not yet in completedSteps — the step is still pending confirmation.
    $state = $this->buildState(
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::CREATE_ORGANIZATION,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [],
      canRollback: false,
      lastRollbackableStep: null,
    );

    $output = OrganizationOnboardingOutputAssembler::fromState($state);

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $output->state);
    self::assertSame($orgId, $output->targetOrganizationId);
    self::assertFalse($output->canRollback);

    $createStep = $output->steps[0];
    self::assertSame('pending', $createStep->status);
    self::assertTrue($createStep->required);
    self::assertTrue($createStep->available);

    $inviteStep = $output->steps[1];
    self::assertSame('blocked', $inviteStep->status);
    self::assertSame('organization_required', $inviteStep->reason);
  }

  #[Test]
  public function testFromStateWithOrganizationAndInvitePending(): void
  {
    $orgId = '550e8400-e29b-41d4-a716-446655440002';

    $state = $this->buildState(
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::INVITE_MEMBERS,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [OrganizationOnboardingStep::CREATE_ORGANIZATION],
      canRollback: true,
      lastRollbackableStep: OrganizationOnboardingStep::CREATE_ORGANIZATION,
    );

    $output = OrganizationOnboardingOutputAssembler::fromState($state);

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $output->state);
    self::assertTrue($output->canRollback);
    self::assertSame('POST', $output->rollbackMethod);
    self::assertSame('/api/onboarding/organization/rollback', $output->rollbackPath);
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $output->lastRollbackableStep);

    $inviteStep = $output->steps[1];
    self::assertSame(OrganizationOnboardingStep::INVITE_MEMBERS, $inviteStep->key);
    self::assertSame('pending', $inviteStep->status);
    self::assertFalse($inviteStep->required);
    self::assertTrue($inviteStep->available);
    self::assertNull($inviteStep->reason);
    self::assertTrue($inviteStep->skippable);
    self::assertSame('/api/organizations/' . $orgId . '/invitations', $inviteStep->actionPath);
    self::assertSame('POST', $inviteStep->skipMethod);
    self::assertSame(
      '/api/onboarding/organization/steps/invite_members/skip',
      $inviteStep->skipPath,
    );

    $createStep = $output->steps[0];
    self::assertTrue($createStep->rollbackAvailable);
    self::assertSame('POST', $createStep->rollbackMethod);
    self::assertSame('/api/onboarding/organization/rollback', $createStep->rollbackPath);

    self::assertFalse($inviteStep->rollbackAvailable);
  }

  #[Test]
  public function testFromStateCompleted(): void
  {
    $orgId = '550e8400-e29b-41d4-a716-446655440003';

    $state = $this->buildState(
      state: OrganizationOnboardingState::COMPLETED,
      nextStep: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [
        OrganizationOnboardingStep::CREATE_ORGANIZATION,
        OrganizationOnboardingStep::INVITE_MEMBERS,
      ],
      canRollback: false,
      lastRollbackableStep: null,
    );

    $output = OrganizationOnboardingOutputAssembler::fromState($state);

    self::assertSame(OrganizationOnboardingState::COMPLETED, $output->state);
    self::assertNull($output->nextStep);
    self::assertFalse($output->canRollback);
    self::assertNull($output->rollbackPath);

    $createStep = $output->steps[0];
    self::assertSame('completed', $createStep->status);
    self::assertFalse($createStep->required);

    $inviteStep = $output->steps[1];
    self::assertSame(OrganizationOnboardingStep::INVITE_MEMBERS, $inviteStep->key);
    self::assertSame('completed', $inviteStep->status);
    self::assertFalse($inviteStep->required);
    self::assertTrue($inviteStep->available);

    self::assertFalse($createStep->rollbackAvailable);
    self::assertFalse($inviteStep->rollbackAvailable);
  }

  #[Test]
  public function testFromStateWithSkippedInviteMembers(): void
  {
    $orgId = '550e8400-e29b-41d4-a716-446655440004';

    $state = $this->buildState(
      state: OrganizationOnboardingState::COMPLETED,
      nextStep: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [OrganizationOnboardingStep::CREATE_ORGANIZATION],
      skippedSteps: [OrganizationOnboardingStep::INVITE_MEMBERS],
      canRollback: false,
      lastRollbackableStep: null,
    );

    $output = OrganizationOnboardingOutputAssembler::fromState($state);

    self::assertSame(OrganizationOnboardingState::COMPLETED, $output->state);
    self::assertFalse($output->canRollback);

    $inviteStep = $output->steps[1];
    self::assertSame(OrganizationOnboardingStep::INVITE_MEMBERS, $inviteStep->key);
    self::assertSame('skipped', $inviteStep->status);
    self::assertFalse($inviteStep->required);
    self::assertTrue($inviteStep->available);
    self::assertFalse($inviteStep->rollbackAvailable);
  }

  /**
   * @param list<string> $completedSteps
   * @param list<string> $skippedSteps
   */
  private function buildState(
    string $state,
    ?string $nextStep,
    ?string $targetOrganizationId,
    ?string $targetOrganizationName,
    array $completedSteps,
    bool $canRollback,
    ?string $lastRollbackableStep,
    ?string $blockedReason = null,
    array $skippedSteps = [],
  ): OrganizationOnboardingSessionState {
    return new OrganizationOnboardingSessionState(
      flow: 'organization',
      state: $state,
      nextStep: $nextStep,
      blockedReason: $blockedReason,
      targetOrganizationId: $targetOrganizationId,
      targetOrganizationName: $targetOrganizationName,
      completedSteps: $completedSteps,
      skippedSteps: $skippedSteps,
      stepHistory: [],
      updatedAt: '2026-02-19T10:00:00+00:00',
      canRollback: $canRollback,
      lastRollbackableStep: $lastRollbackableStep,
    );
  }
}
