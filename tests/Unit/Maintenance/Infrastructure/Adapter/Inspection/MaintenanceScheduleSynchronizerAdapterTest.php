<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Infrastructure\Adapter\Inspection;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\InspectionMaintenanceSynchronizerPort;
use Maintenance\Application\Port\Inbound\MaintenanceSchedulePort;
use Maintenance\Infrastructure\Adapter\Inspection\MaintenanceScheduleSynchronizerAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test MaintenanceScheduleSynchronizerAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceScheduleSynchronizerAdapter::class)]
final class MaintenanceScheduleSynchronizerAdapterTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655501001';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655501002';

  #[Test]
  public function testItImplementsTheInspectionSynchronizerPort(): void
  {
    $adapter = new MaintenanceScheduleSynchronizerAdapter($this->createStub(MaintenanceSchedulePort::class));

    self::assertInstanceOf(InspectionMaintenanceSynchronizerPort::class, $adapter);
  }

  #[Test]
  public function testItForwardsTheClosureToTheMaintenanceSchedulePort(): void
  {
    $closedAt = new DateTimeImmutable('2026-05-04T08:15:00+00:00');

    /** @var MaintenanceSchedulePort&MockObject $schedulePort */
    $schedulePort = $this->createMock(MaintenanceSchedulePort::class);
    $schedulePort->expects(self::once())
      ->method('onInspectionClosed')
      ->with(self::ORGANIZATION_ID, self::EQUIPMENT_ID, $closedAt);

    new MaintenanceScheduleSynchronizerAdapter($schedulePort)
      ->onInspectionClosed(self::ORGANIZATION_ID, self::EQUIPMENT_ID, $closedAt);
  }

  #[Test]
  public function testItLetsASchedulingFailureBubbleUp(): void
  {
    // The Inspection closure must not silently report success when the
    // schedule could not be recomputed: the next due date would be stale.
    $schedulePort = $this->createStub(MaintenanceSchedulePort::class);
    $schedulePort->method('onInspectionClosed')->willThrowException(new RuntimeException('schedule store unreachable'));

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('schedule store unreachable');

    new MaintenanceScheduleSynchronizerAdapter($schedulePort)
      ->onInspectionClosed(self::ORGANIZATION_ID, self::EQUIPMENT_ID, new DateTimeImmutable('2026-05-04T08:15:00+00:00'));
  }
}
