<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules;

use DateTimeImmutable;
use Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy;
use Maintenance\Application\Contract\Directory\TrackableEquipment;
use Maintenance\Application\Contract\Schedule\{MaintenanceSchedulePage, MaintenanceScheduleSnapshot, MaintenanceScheduleView};
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Application\Service\{MaintenanceReminderNotifier, MaintenanceReminderRecipientResolver};
use Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules\{
  RecomputeMaintenanceSchedulesCommand,
  RecomputeMaintenanceSchedulesHandler
};
use Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationNotificationPolicyPort};
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationNotificationSettings};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\VoidResult;
use Shared\Application\Port\Outbound\ClockPort;

/**
 * Test RecomputeMaintenanceSchedulesHandlerTest.
 *
 * Exercises the reminder path through a REAL `MaintenanceReminderNotifier`
 * (final, backed by stubbed cross-module ports) rather than mocking it
 * directly, and asserts on the underlying `NotificationPort::send()` call.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RecomputeMaintenanceSchedulesHandler::class)]
final class RecomputeMaintenanceSchedulesHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testInvokeBootstrapsMissingSchedulesAndDropsDecommissionedOnes(): void
  {
    $newEquipment = new TrackableEquipment('equip-new', self::ORG_ID, 'facility-1', 'fire_extinguisher', 'operational');
    $decommissioned = new TrackableEquipment('equip-old', self::ORG_ID, null, 'sprinkler', 'decommissioned');

    $directory = $this->createMock(MaintenanceEquipmentDirectoryPort::class);
    $directory->expects(self::once())
      ->method('listEquipmentPage')
      ->with(200, 0)
      ->willReturn([$newEquipment, $decommissioned]);

    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('findByOrganizationAndEquipment')->willReturn(null);
    $schedules->expects(self::once())
      ->method('removeByOrganizationAndEquipment')
      ->with(self::ORG_ID, 'equip-old');
    $schedules->expects(self::once())
      ->method('save')
      ->with(self::callback(function (MaintenanceScheduleSnapshot $snapshot): bool {
        self::assertSame('equip-new', $snapshot->equipmentId);
        self::assertNull($snapshot->nextDueAt);
        self::assertSame('overdue', $snapshot->dueStatus);

        return true;
      }))
      ->willReturn($this->makeSchedule('equip-new', null, 'overdue', null));
    $schedules->method('pageForSweep')->willReturn(new MaintenanceSchedulePage([], 0, 200, 0));

    $compliancePolicy = $this->createStub(MaintenanceCompliancePolicyPort::class);
    $compliancePolicy->method('compliancePolicy')->willReturn(new MaintenanceCompliancePolicy(['fire_extinguisher' => 'P90D'], 14));

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $handler = new RecomputeMaintenanceSchedulesHandler(
      $schedules,
      $directory,
      $compliancePolicy,
      new MaintenanceScheduleRecomputePolicy(),
      $this->notifier($notifications),
      $this->clock(),
    );

    $result = $handler->__invoke(new RecomputeMaintenanceSchedulesCommand());

    self::assertInstanceOf(VoidResult::class, $result);
  }

  #[Test]
  public function testInvokeSendsAReminderExactlyOnceForADistinctDueDate(): void
  {
    $directory = $this->createStub(MaintenanceEquipmentDirectoryPort::class);
    $directory->method('listEquipmentPage')->willReturn([]);

    $nextDueAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $dueSchedule = $this->makeSchedule('equip-1', $nextDueAt, 'due_soon', null);

    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('pageForSweep')->willReturn(new MaintenanceSchedulePage([$dueSchedule], 0, 200, 0));
    $schedules->expects(self::once())
      ->method('save')
      ->with(self::callback(function (MaintenanceScheduleSnapshot $snapshot): bool {
        self::assertNotNull($snapshot->remindedFor);
        self::assertNotNull($snapshot->lastRemindedAt);

        return true;
      }))
      ->willReturn($this->makeSchedule('equip-1', $nextDueAt, 'due_soon', $nextDueAt));

    $compliancePolicy = $this->createStub(MaintenanceCompliancePolicyPort::class);
    $compliancePolicy->method('compliancePolicy')->willReturn(new MaintenanceCompliancePolicy(['fire_extinguisher' => 'P90D'], 14));

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'maintenance.inspection_due' === $request->type
        && 'equip-1' === $request->payload['equipmentId']))
      ->willReturn($this->sent());

    // "now" a few days before the due date, inside the 14-day reminder
    // window: due status is `due_soon` (not yet `overdue`).
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($nextDueAt->modify('-7 days'));

    $handler = new RecomputeMaintenanceSchedulesHandler(
      $schedules,
      $directory,
      $compliancePolicy,
      new MaintenanceScheduleRecomputePolicy(),
      $this->notifier($notifications),
      $clock,
    );

    $handler->__invoke(new RecomputeMaintenanceSchedulesCommand());
  }

  #[Test]
  public function testInvokeDoesNotRemindTwiceForTheSameDueDate(): void
  {
    $directory = $this->createStub(MaintenanceEquipmentDirectoryPort::class);
    $directory->method('listEquipmentPage')->willReturn([]);

    $nextDueAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $alreadyReminded = $this->makeSchedule('equip-1', $nextDueAt, 'due_soon', $nextDueAt);

    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('pageForSweep')->willReturn(new MaintenanceSchedulePage([$alreadyReminded], 0, 200, 0));
    $schedules->expects(self::never())->method('save');

    $compliancePolicy = $this->createStub(MaintenanceCompliancePolicyPort::class);
    $compliancePolicy->method('compliancePolicy')->willReturn(new MaintenanceCompliancePolicy(['fire_extinguisher' => 'P90D'], 14));

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    // "now" exactly at the due date: due status stays `due_soon` (no
    // transition to persist) and the reminder marker already matches.
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($nextDueAt);

    $handler = new RecomputeMaintenanceSchedulesHandler(
      $schedules,
      $directory,
      $compliancePolicy,
      new MaintenanceScheduleRecomputePolicy(),
      $this->notifier($notifications),
      $clock,
    );

    $handler->__invoke(new RecomputeMaintenanceSchedulesCommand());
  }

  #[Test]
  public function testInvokeSkipsBootstrappingEquipmentThatAlreadyHasASchedule(): void
  {
    $equipment = new TrackableEquipment('equip-1', self::ORG_ID, 'facility-1', 'fire_extinguisher', 'operational');

    $directory = $this->createMock(MaintenanceEquipmentDirectoryPort::class);
    $directory->expects(self::once())
      ->method('listEquipmentPage')
      ->willReturn([$equipment]);

    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->expects(self::once())
      ->method('findByOrganizationAndEquipment')
      ->with(self::ORG_ID, 'equip-1')
      ->willReturn($this->makeSchedule('equip-1', null, 'overdue', null));
    $schedules->expects(self::never())->method('save');
    $schedules->expects(self::never())->method('removeByOrganizationAndEquipment');
    $schedules->method('pageForSweep')->willReturn(new MaintenanceSchedulePage([], 0, 200, 0));

    $compliancePolicy = $this->createMock(MaintenanceCompliancePolicyPort::class);
    $compliancePolicy->expects(self::never())->method('compliancePolicy');

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $handler = new RecomputeMaintenanceSchedulesHandler(
      $schedules,
      $directory,
      $compliancePolicy,
      new MaintenanceScheduleRecomputePolicy(),
      $this->notifier($notifications),
      $this->clock(),
    );

    self::assertInstanceOf(VoidResult::class, $handler->__invoke(new RecomputeMaintenanceSchedulesCommand()));
  }

  private function makeSchedule(string $equipmentId, ?DateTimeImmutable $nextDueAt, string $dueStatus, ?DateTimeImmutable $remindedFor): MaintenanceScheduleView
  {
    return new MaintenanceScheduleView(
      id: 'schedule-' . $equipmentId,
      organizationId: self::ORG_ID,
      equipmentId: $equipmentId,
      facilityId: 'facility-1',
      equipmentType: 'fire_extinguisher',
      intervalOverride: null,
      lastInspectionClosedAt: null !== $nextDueAt ? $nextDueAt->modify('-90 days') : null,
      nextDueAt: $nextDueAt,
      dueStatus: $dueStatus,
      lastRemindedAt: null,
      remindedFor: $remindedFor,
      createdAt: new DateTimeImmutable('2025-01-01'),
      updatedAt: new DateTimeImmutable('2025-01-01'),
    );
  }

  /**
   * Builds a REAL notifier (final class) backed by a real recipient
   * resolver (also final) and stubbed cross-module ports, so the given
   * `NotificationPort` mock's expectations are the sole assertion surface.
   */
  private function notifier(NotificationPort $notifications): MaintenanceReminderNotifier
  {
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(new OrganizationNotificationSettings());

    $member = OrganizationMember::reconstitute(
      OrganizationMemberId::fromString('550e8400-e29b-41d4-a716-446655440099'),
      OrganizationId::fromString(self::ORG_ID),
      'admin-user',
      true,
      new DateTimeImmutable(),
    );

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationId')->willReturn([$member]);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturn(['organization.maintenance.manage']);

    return new MaintenanceReminderNotifier(
      $notifications,
      $policy,
      new MaintenanceReminderRecipientResolver($memberRepository, $authorization),
    );
  }

  private function sent(): SentNotification
  {
    return new SentNotification(
      '550e8400-e29b-41d4-a716-446655440098',
      'maintenance.inspection_due',
      'Inspection due soon',
      'body',
      [NotificationChannel::MERCURE->value],
      [],
      [NotificationChannel::MERCURE->value => true],
      new DateTimeImmutable(),
      'admin-user',
      null,
    );
  }

  private function clock(): ClockPort
  {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-05T00:00:00+00:00'));

    return $clock;
  }
}
