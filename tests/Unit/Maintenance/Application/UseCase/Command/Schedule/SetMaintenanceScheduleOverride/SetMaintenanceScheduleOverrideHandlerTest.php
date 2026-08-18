<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Command\Schedule\SetMaintenanceScheduleOverride;

use DateTimeImmutable;
use Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy;
use Maintenance\Application\Contract\Schedule\{MaintenanceScheduleSnapshot, MaintenanceScheduleView};
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Application\UseCase\Command\Schedule\SetMaintenanceScheduleOverride\{
  SetMaintenanceScheduleOverrideCommand,
  SetMaintenanceScheduleOverrideHandler,
  SetMaintenanceScheduleOverrideResult
};
use Maintenance\Domain\Event\Schedule\MaintenanceScheduleOverriddenEvent;
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceNotFoundException, MaintenanceValidationException};
use Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

/**
 * Test SetMaintenanceScheduleOverrideHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SetMaintenanceScheduleOverrideHandler::class)]
final class SetMaintenanceScheduleOverrideHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string SCHEDULE_ID = 'schedule-1';

  private const string USER_ID = 'user-1';

  #[Test]
  public function testInvokeSetsAnOverrideAndRecomputesTheSchedule(): void
  {
    $existing = $this->schedule(intervalOverride: null, nextDueAt: new DateTimeImmutable('2026-04-01T00:00:00+00:00'));

    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('findById')->willReturn($existing);
    $schedules->expects(self::once())
      ->method('save')
      ->with(self::callback(function (MaintenanceScheduleSnapshot $snapshot): bool {
        self::assertSame('P30D', $snapshot->intervalOverride);
        self::assertSame('2026-01-31T00:00:00+00:00', $snapshot->nextDueAt?->format('c'));
        self::assertNull($snapshot->remindedFor);

        return true;
      }))
      ->willReturn($this->schedule(intervalOverride: 'P30D', nextDueAt: new DateTimeImmutable('2026-01-31T00:00:00+00:00')));

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.maintenance.manage')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $event): bool => $event instanceof MaintenanceScheduleOverriddenEvent
        && self::SCHEDULE_ID === $event->scheduleId
        && 'P30D' === $event->intervalOverride
        && self::USER_ID === $event->actorUserId));

    $handler = new SetMaintenanceScheduleOverrideHandler(
      $schedules,
      $this->compliancePolicy(),
      new MaintenanceScheduleRecomputePolicy(),
      $authorization,
      $eventDispatcher,
      $this->clock(),
    );

    $result = $handler->__invoke(new SetMaintenanceScheduleOverrideCommand(self::USER_ID, self::SCHEDULE_ID, 'P30D'));

    self::assertInstanceOf(SetMaintenanceScheduleOverrideResult::class, $result);
  }

  #[Test]
  public function testInvokeClearsAnOverrideWhenNullIsGiven(): void
  {
    $existing = $this->schedule(intervalOverride: 'P30D', nextDueAt: new DateTimeImmutable('2026-01-31T00:00:00+00:00'));

    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('findById')->willReturn($existing);
    $schedules->expects(self::once())
      ->method('save')
      ->with(self::callback(function (MaintenanceScheduleSnapshot $snapshot): bool {
        self::assertNull($snapshot->intervalOverride);
        // Organization periodicity for fire_extinguisher is P90D in the stubbed policy.
        self::assertSame('2026-04-01T00:00:00+00:00', $snapshot->nextDueAt?->format('c'));

        return true;
      }))
      ->willReturn($existing);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = new SetMaintenanceScheduleOverrideHandler(
      $schedules,
      $this->compliancePolicy(),
      new MaintenanceScheduleRecomputePolicy(),
      $authorization,
      $eventDispatcher,
      $this->clock(),
    );

    $handler->__invoke(new SetMaintenanceScheduleOverrideCommand(self::USER_ID, self::SCHEDULE_ID, null));
  }

  #[Test]
  public function testInvokeThrowsWhenScheduleIsNotFound(): void
  {
    $schedules = $this->createStub(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('findById')->willReturn(null);

    $handler = new SetMaintenanceScheduleOverrideHandler(
      $schedules,
      $this->createStub(MaintenanceCompliancePolicyPort::class),
      new MaintenanceScheduleRecomputePolicy(),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->createStub(EventDispatcherPort::class),
      $this->clock(),
    );

    $this->expectException(MaintenanceNotFoundException::class);

    $handler->__invoke(new SetMaintenanceScheduleOverrideCommand(self::USER_ID, self::SCHEDULE_ID, 'P30D'));
  }

  #[Test]
  public function testInvokeThrowsWhenPermissionIsMissing(): void
  {
    $schedules = $this->createStub(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('findById')->willReturn($this->schedule(null, null));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $handler = new SetMaintenanceScheduleOverrideHandler(
      $schedules,
      $this->createStub(MaintenanceCompliancePolicyPort::class),
      new MaintenanceScheduleRecomputePolicy(),
      $authorization,
      $this->createStub(EventDispatcherPort::class),
      $this->clock(),
    );

    $this->expectException(MaintenanceAccessDeniedException::class);

    $handler->__invoke(new SetMaintenanceScheduleOverrideCommand(self::USER_ID, self::SCHEDULE_ID, 'P30D'));
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheScheduleIsOutsideTheCallersScope(): void
  {
    $schedules = $this->createStub(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('findById')->willReturn($this->schedule(null, null));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new SetMaintenanceScheduleOverrideHandler(
      $schedules,
      $this->createStub(MaintenanceCompliancePolicyPort::class),
      new MaintenanceScheduleRecomputePolicy(),
      $authorization,
      $eventDispatcher,
      $this->clock(),
    );

    // Not-found rather than access-denied: a 403 would confirm to a caller
    // from another organization that the schedule exists.
    $this->expectException(MaintenanceNotFoundException::class);

    $handler->__invoke(new SetMaintenanceScheduleOverrideCommand(self::USER_ID, self::SCHEDULE_ID, 'P30D'));
  }

  #[Test]
  public function testInvokeThrowsAValidationExceptionForAMalformedOverride(): void
  {
    $schedules = $this->createStub(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('findById')->willReturn($this->schedule(null, null));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $handler = new SetMaintenanceScheduleOverrideHandler(
      $schedules,
      $this->compliancePolicy(),
      new MaintenanceScheduleRecomputePolicy(),
      $authorization,
      $this->createStub(EventDispatcherPort::class),
      $this->clock(),
    );

    $this->expectException(MaintenanceValidationException::class);

    $handler->__invoke(new SetMaintenanceScheduleOverrideCommand(self::USER_ID, self::SCHEDULE_ID, 'not-a-duration'));
  }

  private function schedule(?string $intervalOverride, ?DateTimeImmutable $nextDueAt): MaintenanceScheduleView
  {
    return new MaintenanceScheduleView(
      id: self::SCHEDULE_ID,
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      facilityId: 'facility-1',
      equipmentType: 'fire_extinguisher',
      intervalOverride: $intervalOverride,
      lastInspectionClosedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      nextDueAt: $nextDueAt,
      dueStatus: 'up_to_date',
      lastRemindedAt: null,
      remindedFor: $nextDueAt,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }

  private function compliancePolicy(): MaintenanceCompliancePolicyPort
  {
    $policy = $this->createStub(MaintenanceCompliancePolicyPort::class);
    $policy->method('compliancePolicy')->willReturn(new MaintenanceCompliancePolicy(['fire_extinguisher' => 'P90D'], 14));

    return $policy;
  }

  private function clock(): ClockPort
  {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-10T00:00:00+00:00'));

    return $clock;
  }
}
