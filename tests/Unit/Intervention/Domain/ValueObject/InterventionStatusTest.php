<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\ValueObject;

use Intervention\Domain\ValueObject\InterventionStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionStatus.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionStatus::class)]
final class InterventionStatusTest extends TestCase
{
  #[Test]
  public function testBackingValuesAreStable(): void
  {
    self::assertSame('draft', InterventionStatus::DRAFT->value);
    self::assertSame('planned', InterventionStatus::PLANNED->value);
    self::assertSame('in_progress', InterventionStatus::IN_PROGRESS->value);
    self::assertSame('submitted', InterventionStatus::SUBMITTED->value);
    self::assertSame('changes_requested', InterventionStatus::CHANGES_REQUESTED->value);
    self::assertSame('published', InterventionStatus::PUBLISHED->value);
    self::assertSame('abandoned', InterventionStatus::ABANDONED->value);
  }

  #[Test]
  public function testMutableStatusesAreMutable(): void
  {
    self::assertTrue(InterventionStatus::DRAFT->isMutable());
    self::assertTrue(InterventionStatus::PLANNED->isMutable());
    self::assertTrue(InterventionStatus::IN_PROGRESS->isMutable());
    self::assertTrue(InterventionStatus::SUBMITTED->isMutable());
    self::assertTrue(InterventionStatus::CHANGES_REQUESTED->isMutable());
  }

  #[Test]
  public function testTerminalStatusesAreNotMutable(): void
  {
    self::assertFalse(InterventionStatus::PUBLISHED->isMutable());
    self::assertFalse(InterventionStatus::ABANDONED->isMutable());
  }
}
