<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Service;

use Intervention\Domain\Service\InterventionMutabilityPolicy;
use Intervention\Domain\ValueObject\InterventionStatus;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionMutabilityPolicyTest.
 *
 * @category Domain Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionMutabilityPolicyTest extends TestCase
{
  /**
   * @return iterable<string, array{InterventionStatus, bool}>
   */
  public static function mutableStatuses(): iterable
  {
    yield 'draft' => [InterventionStatus::DRAFT, true];
    yield 'planned' => [InterventionStatus::PLANNED, true];
    yield 'in_progress' => [InterventionStatus::IN_PROGRESS, true];
    yield 'submitted' => [InterventionStatus::SUBMITTED, true];
    yield 'changes_requested' => [InterventionStatus::CHANGES_REQUESTED, true];
    yield 'published' => [InterventionStatus::PUBLISHED, false];
    yield 'abandoned' => [InterventionStatus::ABANDONED, false];
  }

  #[Test]
  #[DataProvider('mutableStatuses')]
  public function itMatchesTheStatusOwnMutabilityFlag(InterventionStatus $status, bool $expected): void
  {
    self::assertSame($expected, new InterventionMutabilityPolicy()->isMutable($status));
  }

  /**
   * @return iterable<string, array{InterventionStatus, bool}>
   */
  public static function scopeMutableStatuses(): iterable
  {
    yield 'draft' => [InterventionStatus::DRAFT, true];
    yield 'planned' => [InterventionStatus::PLANNED, false];
    yield 'in_progress' => [InterventionStatus::IN_PROGRESS, false];
    yield 'submitted' => [InterventionStatus::SUBMITTED, false];
    yield 'changes_requested' => [InterventionStatus::CHANGES_REQUESTED, false];
    yield 'published' => [InterventionStatus::PUBLISHED, false];
    yield 'abandoned' => [InterventionStatus::ABANDONED, false];
  }

  #[Test]
  #[DataProvider('scopeMutableStatuses')]
  public function itKeepsTheSiteEditableInDraftOnly(InterventionStatus $status, bool $expected): void
  {
    self::assertSame($expected, new InterventionMutabilityPolicy()->isScopeMutable($status));
  }

  /**
   * @return iterable<string, array{InterventionStatus, bool}>
   */
  public static function ownershipMutableStatuses(): iterable
  {
    yield 'draft' => [InterventionStatus::DRAFT, true];
    yield 'planned' => [InterventionStatus::PLANNED, true];
    yield 'in_progress' => [InterventionStatus::IN_PROGRESS, false];
    yield 'submitted' => [InterventionStatus::SUBMITTED, false];
    yield 'changes_requested' => [InterventionStatus::CHANGES_REQUESTED, false];
    yield 'published' => [InterventionStatus::PUBLISHED, false];
    yield 'abandoned' => [InterventionStatus::ABANDONED, false];
  }

  #[Test]
  #[DataProvider('ownershipMutableStatuses')]
  public function itKeepsTheResponsibleEditableThroughDraftAndPlanned(InterventionStatus $status, bool $expected): void
  {
    self::assertSame($expected, new InterventionMutabilityPolicy()->isOwnershipMutable($status));
  }

  /**
   * @return iterable<string, array{InterventionStatus, bool}>
   */
  public static function scheduleMutableStatuses(): iterable
  {
    yield 'draft' => [InterventionStatus::DRAFT, true];
    yield 'planned' => [InterventionStatus::PLANNED, true];
    yield 'in_progress' => [InterventionStatus::IN_PROGRESS, true];
    yield 'submitted' => [InterventionStatus::SUBMITTED, false];
    yield 'changes_requested' => [InterventionStatus::CHANGES_REQUESTED, true];
    yield 'published' => [InterventionStatus::PUBLISHED, false];
    yield 'abandoned' => [InterventionStatus::ABANDONED, false];
  }

  #[Test]
  #[DataProvider('scheduleMutableStatuses')]
  public function itFreezesTheScheduleOnlyWhileSubmitted(InterventionStatus $status, bool $expected): void
  {
    self::assertSame($expected, new InterventionMutabilityPolicy()->isScheduleMutable($status));
  }
}
