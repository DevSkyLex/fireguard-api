<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Domain\Model\OrganizationOnboardingSession;

use DateTimeImmutable;
use Onboarding\Domain\Model\OrganizationOnboardingSession\OrganizationOnboardingSession;
use Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction\DeleteOrganizationRollbackAction;
use Onboarding\Domain\ValueObject\{OrganizationOnboardingState, OrganizationOnboardingStep};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationOnboardingSession::class)]
final class OrganizationOnboardingSessionTest extends TestCase
{
  // #region start

  #[Test]
  public function testStartCreatesSessionWithCorrectInitialState(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    self::assertSame('550e8400-e29b-41d4-a716-550000000001', $session->id());
    self::assertSame('550e8400-e29b-41d4-a716-550000000002', $session->userId());
    self::assertSame('organization', $session->flow());
    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $session->state());
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $session->nextStep());
    self::assertNull($session->blockedReason());
    self::assertNull($session->targetOrganizationId());
    self::assertNull($session->targetOrganizationName());
    self::assertSame([], $session->completedSteps());
    self::assertSame([], $session->skippedSteps());
    self::assertSame([], $session->rollbackStack());
    self::assertSame([], $session->stepHistory());
  }

  // #endregion

  // #region reconstitute

  #[Test]
  public function testReconstituteFiltersInvalidCompletedAndSkippedSteps(): void
  {
    $session = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::INVITE_MEMBERS,
      blockedReason: null,
      targetOrganizationId: null,
      targetOrganizationName: null,
      completedSteps: [OrganizationOnboardingStep::CREATE_ORGANIZATION, 'invalid_step'],
      skippedSteps: ['also_invalid', OrganizationOnboardingStep::INVITE_MEMBERS],
      rollbackStack: [],
      stepHistory: [],
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
    );

    self::assertSame([OrganizationOnboardingStep::CREATE_ORGANIZATION], $session->completedSteps());
    self::assertSame([OrganizationOnboardingStep::INVITE_MEMBERS], $session->skippedSteps());
  }

  #[Test]
  public function testReconstitutePreservesAllFields(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T10:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02T10:00:00+00:00');
    $rollback = new DeleteOrganizationRollbackAction('org-123');

    $session = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-550000000010',
      userId: '550e8400-e29b-41d4-a716-550000000011',
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::INVITE_MEMBERS,
      blockedReason: null,
      targetOrganizationId: 'org-123',
      targetOrganizationName: 'My Org',
      completedSteps: [OrganizationOnboardingStep::CREATE_ORGANIZATION],
      skippedSteps: [],
      rollbackStack: [$rollback],
      stepHistory: [],
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );

    self::assertSame('only', 'only');
    self::assertSame('org-123', $session->targetOrganizationId());
    self::assertSame('My Org', $session->targetOrganizationName());
    self::assertSame($createdAt, $session->createdAt());
    self::assertSame($updatedAt, $session->updatedAt());
    self::assertSame([$rollback], $session->rollbackStack());
  }

  // #endregion

  // #region markStepCompleted

  #[Test]
  public function testMarkStepCompletedAddsToCompletedAndHistory(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->markStepCompleted(OrganizationOnboardingStep::CREATE_ORGANIZATION);

    self::assertSame([OrganizationOnboardingStep::CREATE_ORGANIZATION], $session->completedSteps());
    self::assertCount(1, $session->stepHistory());
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $session->stepHistory()[0]->stepKey);
    self::assertFalse($session->stepHistory()[0]->skipped);
  }

  #[Test]
  public function testMarkStepCompletedIsIdempotent(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->markStepCompleted(OrganizationOnboardingStep::CREATE_ORGANIZATION);
    $updatedAtAfterFirst = $session->updatedAt();

    $session->markStepCompleted(OrganizationOnboardingStep::CREATE_ORGANIZATION);

    self::assertSame([OrganizationOnboardingStep::CREATE_ORGANIZATION], $session->completedSteps());
    self::assertCount(1, $session->stepHistory());
    self::assertSame($updatedAtAfterFirst, $session->updatedAt());
  }

  #[Test]
  public function testMarkStepCompletedIgnoresInvalidStep(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->markStepCompleted('not_a_valid_step');

    self::assertSame([], $session->completedSteps());
    self::assertSame([], $session->stepHistory());
  }

  // #endregion

  // #region markStepPending

  #[Test]
  public function testMarkStepPendingRemovesFromCompletedSteps(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->markStepCompleted(OrganizationOnboardingStep::CREATE_ORGANIZATION);
    $session->markStepPending(OrganizationOnboardingStep::CREATE_ORGANIZATION);

    self::assertSame([], $session->completedSteps());
  }

  #[Test]
  public function testMarkStepPendingIsNoopWhenStepNotCompleted(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $before = $session->updatedAt();
    $session->markStepPending(OrganizationOnboardingStep::CREATE_ORGANIZATION);

    self::assertSame($before, $session->updatedAt());
  }

  // #endregion

  // #region markStepSkipped

  #[Test]
  public function testMarkStepSkippedAddsToSkippedAndHistory(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->markStepSkipped(OrganizationOnboardingStep::INVITE_MEMBERS);

    self::assertSame([OrganizationOnboardingStep::INVITE_MEMBERS], $session->skippedSteps());
    self::assertCount(1, $session->stepHistory());
    self::assertSame(OrganizationOnboardingStep::INVITE_MEMBERS, $session->stepHistory()[0]->stepKey);
    self::assertTrue($session->stepHistory()[0]->skipped);
  }

  #[Test]
  public function testMarkStepSkippedIsIdempotent(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->markStepSkipped(OrganizationOnboardingStep::INVITE_MEMBERS);
    $updatedAtAfterFirst = $session->updatedAt();

    $session->markStepSkipped(OrganizationOnboardingStep::INVITE_MEMBERS);

    self::assertSame([OrganizationOnboardingStep::INVITE_MEMBERS], $session->skippedSteps());
    self::assertCount(1, $session->stepHistory());
    self::assertSame($updatedAtAfterFirst, $session->updatedAt());
  }

  #[Test]
  public function testMarkStepSkippedIgnoresInvalidStep(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->markStepSkipped('not_a_valid_step');

    self::assertSame([], $session->skippedSteps());
    self::assertSame([], $session->stepHistory());
  }

  // #endregion

  // #region removeSkippedStep

  #[Test]
  public function testRemoveSkippedStepRemovesFromSkippedList(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->markStepSkipped(OrganizationOnboardingStep::INVITE_MEMBERS);
    $session->removeSkippedStep(OrganizationOnboardingStep::INVITE_MEMBERS);

    self::assertSame([], $session->skippedSteps());
  }

  #[Test]
  public function testRemoveSkippedStepIsNoopWhenNotSkipped(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $before = $session->updatedAt();
    $session->removeSkippedStep(OrganizationOnboardingStep::INVITE_MEMBERS);

    self::assertSame($before, $session->updatedAt());
  }

  // #endregion

  // #region rollback stack LIFO

  #[Test]
  public function testRollbackStackLIFOBehavior(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $action1 = new DeleteOrganizationRollbackAction('org-id-first');
    $action2 = new DeleteOrganizationRollbackAction('org-id-last');

    $session->pushRollbackAction($action1);
    $session->pushRollbackAction($action2);

    self::assertSame($action2, $session->peekRollbackAction());

    $popped = $session->popRollbackAction();

    self::assertSame($action2, $popped);
    self::assertSame($action1, $session->peekRollbackAction());
  }

  #[Test]
  public function testPopRollbackActionOnEmptyStackReturnsNull(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    self::assertNull($session->peekRollbackAction());
    self::assertNull($session->popRollbackAction());
  }

  #[Test]
  public function testClearRollbackStackEmptiesAllActions(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->pushRollbackAction(new DeleteOrganizationRollbackAction('org-id'));
    $session->clearRollbackStack();

    self::assertSame([], $session->rollbackStack());
    self::assertNull($session->peekRollbackAction());
  }

  #[Test]
  public function testClearStepHistoryEmptiesRecordedEntries(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->markStepCompleted(OrganizationOnboardingStep::CREATE_ORGANIZATION);
    $session->markStepSkipped(OrganizationOnboardingStep::INVITE_MEMBERS);

    $session->clearStepHistory();

    self::assertSame([], $session->stepHistory());
  }

  #[Test]
  public function testClearRollbackStackIsNoopWhenAlreadyEmpty(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $before = $session->updatedAt();
    $session->clearRollbackStack();

    self::assertSame($before, $session->updatedAt());
  }

  // #endregion

  // #region target organization

  #[Test]
  public function testSetTargetOrganizationUpdatesFields(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->setTargetOrganization('org-1', 'My Org');

    self::assertSame('org-1', $session->targetOrganizationId());
    self::assertSame('My Org', $session->targetOrganizationName());
  }

  #[Test]
  public function testSetTargetOrganizationDirtyCheckGuardSkipsTouchWhenUnchanged(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->setTargetOrganization('org-1', 'My Org');
    $updatedAtAfterFirst = $session->updatedAt();

    $session->setTargetOrganization('org-1', 'My Org');

    self::assertSame($updatedAtAfterFirst, $session->updatedAt());
  }

  #[Test]
  public function testClearTargetOrganizationNullsOutFields(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->setTargetOrganization('org-1', 'My Org');
    $session->clearTargetOrganization();

    self::assertNull($session->targetOrganizationId());
    self::assertNull($session->targetOrganizationName());
  }

  #[Test]
  public function testClearTargetOrganizationDirtyCheckGuardSkipsTouchWhenAlreadyNull(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $before = $session->updatedAt();
    $session->clearTargetOrganization();

    self::assertSame($before, $session->updatedAt());
  }

  // #endregion

  // #region state transitions

  #[Test]
  public function testSetInProgressTransitionUpdatesNextStep(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->setInProgress(OrganizationOnboardingStep::INVITE_MEMBERS);

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $session->state());
    self::assertSame(OrganizationOnboardingStep::INVITE_MEMBERS, $session->nextStep());
    self::assertNull($session->blockedReason());
  }

  #[Test]
  public function testSetInProgressDirtyCheckGuardSkipsTouchWhenUnchanged(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    // Initially already IN_PROGRESS / CREATE_ORGANIZATION
    $before = $session->updatedAt();
    $session->setInProgress(OrganizationOnboardingStep::CREATE_ORGANIZATION);

    self::assertSame($before, $session->updatedAt());
  }

  #[Test]
  public function testSetBlockedTransitionUpdatesState(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->setBlocked('org_missing');

    self::assertSame(OrganizationOnboardingState::BLOCKED, $session->state());
    self::assertNull($session->nextStep());
    self::assertSame('org_missing', $session->blockedReason());
  }

  #[Test]
  public function testSetBlockedDirtyCheckGuardSkipsTouchWhenUnchanged(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->setBlocked('same_reason');
    $updatedAtAfterFirst = $session->updatedAt();

    $session->setBlocked('same_reason');

    self::assertSame($updatedAtAfterFirst, $session->updatedAt());
  }

  #[Test]
  public function testSetCompletedTransitionUpdatesState(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->setCompleted();

    self::assertSame(OrganizationOnboardingState::COMPLETED, $session->state());
    self::assertNull($session->nextStep());
    self::assertNull($session->blockedReason());
  }

  #[Test]
  public function testSetCompletedDirtyCheckGuardSkipsTouchWhenAlreadyCompleted(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-550000000001',
      userId: '550e8400-e29b-41d4-a716-550000000002',
    );

    $session->setCompleted();
    $updatedAtAfterFirst = $session->updatedAt();

    $session->setCompleted();

    self::assertSame($updatedAtAfterFirst, $session->updatedAt());
  }

  // #endregion
}
