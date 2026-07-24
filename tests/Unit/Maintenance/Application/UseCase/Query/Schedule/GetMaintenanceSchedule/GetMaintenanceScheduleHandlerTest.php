<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Query\Schedule\GetMaintenanceSchedule;

use DateTimeImmutable;
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleView;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Application\UseCase\Query\Schedule\GetMaintenanceSchedule\{
  GetMaintenanceScheduleHandler,
  GetMaintenanceScheduleQuery,
  GetMaintenanceScheduleResult
};
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetMaintenanceScheduleHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetMaintenanceScheduleHandler::class)]
final class GetMaintenanceScheduleHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string SCHEDULE_ID = 'schedule-1';

  private const string USER_ID = 'user-1';

  #[Test]
  public function testInvokeReturnsTheScheduleWhenAuthorized(): void
  {
    $schedule = $this->schedule();

    $schedules = $this->createStub(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('findById')->willReturn($schedule);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handler = new GetMaintenanceScheduleHandler($schedules, $authorization);

    $result = $handler->__invoke(new GetMaintenanceScheduleQuery(self::USER_ID, self::SCHEDULE_ID));

    self::assertInstanceOf(GetMaintenanceScheduleResult::class, $result);
    self::assertSame($schedule, $result->schedule);
  }

  #[Test]
  public function testInvokeThrowsWhenScheduleIsNotFound(): void
  {
    $schedules = $this->createStub(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('findById')->willReturn(null);

    $handler = new GetMaintenanceScheduleHandler(
      $schedules,
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $this->expectException(MaintenanceNotFoundException::class);

    $handler->__invoke(new GetMaintenanceScheduleQuery(self::USER_ID, self::SCHEDULE_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenPermissionIsMissing(): void
  {
    $schedules = $this->createStub(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('findById')->willReturn($this->schedule());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $handler = new GetMaintenanceScheduleHandler($schedules, $authorization);

    $this->expectException(MaintenanceAccessDeniedException::class);

    $handler->__invoke(new GetMaintenanceScheduleQuery(self::USER_ID, self::SCHEDULE_ID));
  }

  private function schedule(): MaintenanceScheduleView
  {
    return new MaintenanceScheduleView(
      id: self::SCHEDULE_ID,
      organizationId: self::ORG_ID,
      equipmentId: 'equip-1',
      facilityId: 'facility-1',
      equipmentType: 'fire_extinguisher',
      intervalOverride: null,
      lastInspectionClosedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      nextDueAt: new DateTimeImmutable('2026-04-01T00:00:00+00:00'),
      dueStatus: 'up_to_date',
      lastRemindedAt: null,
      remindedFor: null,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
}
