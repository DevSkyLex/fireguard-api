<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Service;

use Intervention\Domain\Exception\{InterventionConflictException, InterventionValidationException};
use Intervention\Domain\Service\InterventionWorkItemTransitionPolicy;
use Intervention\Domain\ValueObject\InterventionWorkItemStatus;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

final class InterventionWorkItemTransitionPolicyTest extends TestCase
{
  /**
   * @return iterable<string, array{InterventionWorkItemStatus, InterventionWorkItemStatus, ?string}>
   */
  public static function allowedTransitions(): iterable
  {
    yield 'start work' => [InterventionWorkItemStatus::PLANNED, InterventionWorkItemStatus::IN_PROGRESS, null];
    yield 'plan straight to completed' => [InterventionWorkItemStatus::PLANNED, InterventionWorkItemStatus::COMPLETED, null];
    yield 'plan straight to skipped' => [InterventionWorkItemStatus::PLANNED, InterventionWorkItemStatus::SKIPPED, 'Equipment removed from site'];
    yield 'finish work' => [InterventionWorkItemStatus::IN_PROGRESS, InterventionWorkItemStatus::COMPLETED, null];
    yield 'skip in-progress work' => [InterventionWorkItemStatus::IN_PROGRESS, InterventionWorkItemStatus::SKIPPED, 'Access denied on site'];
    yield 'unplan in-progress work' => [InterventionWorkItemStatus::IN_PROGRESS, InterventionWorkItemStatus::PLANNED, null];
    yield 'reopen completed work' => [InterventionWorkItemStatus::COMPLETED, InterventionWorkItemStatus::IN_PROGRESS, null];
    yield 'uncheck completed work back to planned' => [InterventionWorkItemStatus::COMPLETED, InterventionWorkItemStatus::PLANNED, null];
    yield 'replan skipped work' => [InterventionWorkItemStatus::SKIPPED, InterventionWorkItemStatus::PLANNED, null];
  }

  #[Test]
  #[DataProvider('allowedTransitions')]
  public function itAllowsWorkflowTransitions(InterventionWorkItemStatus $from, InterventionWorkItemStatus $to, ?string $skipReason): void
  {
    new InterventionWorkItemTransitionPolicy()->assertAllowed($from, $to, $skipReason);
    self::addToAssertionCount(1);
  }

  #[Test]
  public function itTreatsATransitionToTheSameStatusAsANoOp(): void
  {
    new InterventionWorkItemTransitionPolicy()->assertAllowed(InterventionWorkItemStatus::COMPLETED, InterventionWorkItemStatus::COMPLETED);

    self::addToAssertionCount(1);
  }

  /**
   * @return iterable<string, array{InterventionWorkItemStatus, InterventionWorkItemStatus}>
   */
  public static function refusedTransitions(): iterable
  {
    yield 'completed cannot skip' => [InterventionWorkItemStatus::COMPLETED, InterventionWorkItemStatus::SKIPPED];
    yield 'skipped cannot go directly to completed' => [InterventionWorkItemStatus::SKIPPED, InterventionWorkItemStatus::COMPLETED];
    yield 'skipped cannot go directly to in progress' => [InterventionWorkItemStatus::SKIPPED, InterventionWorkItemStatus::IN_PROGRESS];
  }

  #[Test]
  #[DataProvider('refusedTransitions')]
  public function itRefusesWorkflowIllegalTransitions(InterventionWorkItemStatus $from, InterventionWorkItemStatus $to): void
  {
    $this->expectException(InterventionConflictException::class);

    new InterventionWorkItemTransitionPolicy()->assertAllowed($from, $to);
  }

  #[Test]
  public function itRequiresANonEmptySkipReason(): void
  {
    $this->expectException(InterventionValidationException::class);

    new InterventionWorkItemTransitionPolicy()->assertAllowed(InterventionWorkItemStatus::PLANNED, InterventionWorkItemStatus::SKIPPED, null);
  }

  #[Test]
  public function itRequiresANonBlankSkipReason(): void
  {
    $this->expectException(InterventionValidationException::class);

    new InterventionWorkItemTransitionPolicy()->assertAllowed(InterventionWorkItemStatus::PLANNED, InterventionWorkItemStatus::SKIPPED, '   ');
  }

  #[Test]
  public function itListsTheWorkflowLegalTransitionsFromEachStatus(): void
  {
    $policy = new InterventionWorkItemTransitionPolicy();

    self::assertSame(
      [InterventionWorkItemStatus::IN_PROGRESS, InterventionWorkItemStatus::COMPLETED, InterventionWorkItemStatus::SKIPPED],
      $policy->allowedFrom(InterventionWorkItemStatus::PLANNED),
    );
    self::assertSame(
      [InterventionWorkItemStatus::COMPLETED, InterventionWorkItemStatus::SKIPPED, InterventionWorkItemStatus::PLANNED],
      $policy->allowedFrom(InterventionWorkItemStatus::IN_PROGRESS),
    );
    self::assertSame(
      [InterventionWorkItemStatus::IN_PROGRESS, InterventionWorkItemStatus::PLANNED],
      $policy->allowedFrom(InterventionWorkItemStatus::COMPLETED),
    );
    self::assertSame(
      [InterventionWorkItemStatus::PLANNED],
      $policy->allowedFrom(InterventionWorkItemStatus::SKIPPED),
    );
  }
}
