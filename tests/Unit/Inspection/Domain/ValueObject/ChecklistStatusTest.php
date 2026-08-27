<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\ValueObject;

use Inspection\Domain\ValueObject\ChecklistStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ChecklistStatus.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ChecklistStatus::class)]
final class ChecklistStatusTest extends TestCase
{
  #[Test]
  public function itExposesAllValues(): void
  {
    self::assertSame(['active', 'archived'], ChecklistStatus::values());
  }

  #[Test]
  public function itReportsArchivedState(): void
  {
    self::assertTrue(ChecklistStatus::ARCHIVED->isArchived());
    self::assertFalse(ChecklistStatus::ACTIVE->isArchived());
  }
}
