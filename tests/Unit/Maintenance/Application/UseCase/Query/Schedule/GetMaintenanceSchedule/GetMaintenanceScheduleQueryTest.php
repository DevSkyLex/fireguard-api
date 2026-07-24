<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Query\Schedule\GetMaintenanceSchedule;

use Maintenance\Application\UseCase\Query\Schedule\GetMaintenanceSchedule\GetMaintenanceScheduleQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\QueryMessage;

/**
 * Test GetMaintenanceScheduleQuery.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetMaintenanceScheduleQuery::class)]
final class GetMaintenanceScheduleQueryTest extends TestCase
{
  #[Test]
  public function testRoundTripsItsProperties(): void
  {
    $query = new GetMaintenanceScheduleQuery('user-1', 'schedule-1');

    self::assertInstanceOf(QueryMessage::class, $query);
    self::assertSame('user-1', $query->userId);
    self::assertSame('schedule-1', $query->scheduleId);
  }
}
