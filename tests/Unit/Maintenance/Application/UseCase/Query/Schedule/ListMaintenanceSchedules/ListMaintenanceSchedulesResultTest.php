<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Query\Schedule\ListMaintenanceSchedules;

use Maintenance\Application\Contract\Schedule\MaintenanceSchedulePage;
use Maintenance\Application\UseCase\Query\Schedule\ListMaintenanceSchedules\ListMaintenanceSchedulesResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test ListMaintenanceSchedulesResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListMaintenanceSchedulesResult::class)]
final class ListMaintenanceSchedulesResultTest extends TestCase
{
  #[Test]
  public function testExposesThePage(): void
  {
    $page = new MaintenanceSchedulePage([], 1, 30, 0);

    $result = new ListMaintenanceSchedulesResult($page);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame($page, $result->page);
  }
}
