<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Service;

use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Domain\Service\InterventionTransitionPolicy;
use Intervention\Domain\ValueObject\InterventionStatus;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

final class InterventionTransitionPolicyTest extends TestCase
{
  /**
   * @return iterable<string, array{InterventionStatus, InterventionStatus}>
   */
  public static function allowedTransitions(): iterable
  {
    yield 'prepare to planned' => [InterventionStatus::DRAFT, InterventionStatus::PLANNED];
    yield 'first field work' => [InterventionStatus::PLANNED, InterventionStatus::IN_PROGRESS];
    yield 'submit for review' => [InterventionStatus::IN_PROGRESS, InterventionStatus::SUBMITTED];
    yield 'request changes' => [InterventionStatus::SUBMITTED, InterventionStatus::CHANGES_REQUESTED];
    yield 'resume corrections' => [InterventionStatus::CHANGES_REQUESTED, InterventionStatus::IN_PROGRESS];
  }

  #[Test]
  #[DataProvider('allowedTransitions')]
  public function itAllowsWorkflowTransitions(InterventionStatus $from, InterventionStatus $to): void
  {
    new InterventionTransitionPolicy()->assertAllowed($from, $to);
    self::addToAssertionCount(1);
  }

  #[Test]
  public function itKeepsPublishedInterventionsImmutable(): void
  {
    $this->expectException(InterventionConflictException::class);

    new InterventionTransitionPolicy()->assertAllowed(InterventionStatus::PUBLISHED, InterventionStatus::IN_PROGRESS);
  }

  #[Test]
  public function itListsTheWorkflowLegalTransitionsFromEachStatus(): void
  {
    $policy = new InterventionTransitionPolicy();

    self::assertSame(
      [InterventionStatus::IN_PROGRESS, InterventionStatus::SUBMITTED, InterventionStatus::ABANDONED],
      $policy->allowedFrom(InterventionStatus::CHANGES_REQUESTED),
    );
    self::assertSame(
      [InterventionStatus::PLANNED, InterventionStatus::ABANDONED],
      $policy->allowedFrom(InterventionStatus::DRAFT),
    );
  }

  #[Test]
  public function itNeverAllowsTransitionsFromTerminalStatuses(): void
  {
    $policy = new InterventionTransitionPolicy();

    self::assertSame([], $policy->allowedFrom(InterventionStatus::PUBLISHED));
    self::assertSame([], $policy->allowedFrom(InterventionStatus::ABANDONED));
  }
}
