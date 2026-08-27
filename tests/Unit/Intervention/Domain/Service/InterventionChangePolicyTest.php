<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Service;

use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Domain\Service\InterventionChangePolicy;
use Intervention\Domain\ValueObject\{InterventionChangeStatus, InterventionStatus};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

final class InterventionChangePolicyTest extends TestCase
{
  private InterventionChangePolicy $policy;

  protected function setUp(): void
  {
    $this->policy = new InterventionChangePolicy();
  }

  #[Test]
  public function itAllowsFieldAgentsToCreateAndEditChangesDuringExecution(): void
  {
    $this->policy->assertCanCreate(InterventionStatus::IN_PROGRESS);
    $this->policy->assertCanEditPatch(InterventionStatus::CHANGES_REQUESTED);

    self::addToAssertionCount(2);
  }

  #[Test]
  public function itAllowsReviewersToRejectButNotEditSubmittedChanges(): void
  {
    $this->policy->assertCanChangeStatus(InterventionStatus::SUBMITTED, InterventionChangeStatus::REJECTED);

    $this->expectException(InterventionConflictException::class);
    $this->policy->assertCanEditPatch(InterventionStatus::SUBMITTED);
  }

  #[Test]
  public function itRejectsNewChangesAfterSubmission(): void
  {
    $this->expectException(InterventionConflictException::class);
    $this->policy->assertCanCreate(InterventionStatus::SUBMITTED);
  }

  #[Test]
  public function itAllowsDeletingAChangeWhileFieldWorkIsActive(): void
  {
    $this->policy->assertCanDelete(InterventionStatus::IN_PROGRESS);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itRejectsDeletingAChangeOnceTheInterventionLeavesFieldWork(): void
  {
    $this->expectException(InterventionConflictException::class);
    $this->policy->assertCanDelete(InterventionStatus::SUBMITTED);
  }

  #[Test]
  public function itRejectsANonRejectionStatusChangeOnASubmittedIntervention(): void
  {
    $this->expectException(InterventionConflictException::class);
    $this->policy->assertCanChangeStatus(InterventionStatus::SUBMITTED, InterventionChangeStatus::PROPOSED);
  }

  /**
   * @return iterable<string, array{InterventionChangeStatus, InterventionChangeStatus}>
   */
  public static function allowedTransitions(): iterable
  {
    yield 'reject a proposed change' => [InterventionChangeStatus::PROPOSED, InterventionChangeStatus::REJECTED];
    yield 'apply a proposed change on publication' => [InterventionChangeStatus::PROPOSED, InterventionChangeStatus::APPLIED];
    yield 'reopen a rejected change' => [InterventionChangeStatus::REJECTED, InterventionChangeStatus::PROPOSED];
  }

  #[Test]
  #[DataProvider('allowedTransitions')]
  public function itAllowsTheChangeStatusStateMachineTransitions(InterventionChangeStatus $from, InterventionChangeStatus $to): void
  {
    $this->policy->assertTransitionAllowed($from, $to);
    self::addToAssertionCount(1);
  }

  #[Test]
  public function itTreatsAChangeStatusTransitionToTheSameStatusAsANoOp(): void
  {
    $this->policy->assertTransitionAllowed(InterventionChangeStatus::PROPOSED, InterventionChangeStatus::PROPOSED);

    self::addToAssertionCount(1);
  }

  /**
   * @return iterable<string, array{InterventionChangeStatus, InterventionChangeStatus}>
   */
  public static function refusedTransitions(): iterable
  {
    yield 'applied is terminal' => [InterventionChangeStatus::APPLIED, InterventionChangeStatus::PROPOSED];
    yield 'rejected cannot go straight to applied' => [InterventionChangeStatus::REJECTED, InterventionChangeStatus::APPLIED];
  }

  #[Test]
  #[DataProvider('refusedTransitions')]
  public function itRefusesChangeStatusStateMachineIllegalTransitions(InterventionChangeStatus $from, InterventionChangeStatus $to): void
  {
    $this->expectException(InterventionConflictException::class);
    $this->policy->assertTransitionAllowed($from, $to);
  }

  #[Test]
  public function itNeverAllowsTransitionsFromTheAppliedTerminalStatus(): void
  {
    self::assertSame([], $this->policy->allowedFrom(InterventionChangeStatus::APPLIED));
  }
}
