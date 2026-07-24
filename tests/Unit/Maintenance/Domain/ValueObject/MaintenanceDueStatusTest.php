<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\ValueObject;

use Maintenance\Domain\ValueObject\MaintenanceDueStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceDueStatus.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceDueStatus::class)]
final class MaintenanceDueStatusTest extends TestCase
{
  #[Test]
  public function testCasesCarryTheirStringValues(): void
  {
    self::assertSame('unscheduled', MaintenanceDueStatus::UNSCHEDULED->value);
    self::assertSame('up_to_date', MaintenanceDueStatus::UP_TO_DATE->value);
    self::assertSame('due_soon', MaintenanceDueStatus::DUE_SOON->value);
    self::assertSame('overdue', MaintenanceDueStatus::OVERDUE->value);
  }

  #[Test]
  public function testValuesReturnsEveryCaseValue(): void
  {
    self::assertSame(
      ['unscheduled', 'up_to_date', 'due_soon', 'overdue'],
      MaintenanceDueStatus::values(),
    );
  }

  #[Test]
  public function testFromResolvesAKnownValue(): void
  {
    self::assertSame(MaintenanceDueStatus::OVERDUE, MaintenanceDueStatus::from('overdue'));
  }
}
