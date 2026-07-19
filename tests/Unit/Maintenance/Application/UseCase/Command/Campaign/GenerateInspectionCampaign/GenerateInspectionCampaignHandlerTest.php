<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign;

use DateTimeImmutable;
use Intervention\Application\Contract\Draft\{CreateInterventionDraftRequest, CreatedInterventionDraft};
use Intervention\Application\Port\Inbound\InterventionDraftFactoryPort;
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleView;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign\{
  GenerateInspectionCampaignCommand,
  GenerateInspectionCampaignHandler,
  GenerateInspectionCampaignResult
};
use Maintenance\Domain\Exception\MaintenanceValidationException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test GenerateInspectionCampaignHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GenerateInspectionCampaignHandler::class)]
final class GenerateInspectionCampaignHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = 'user-1';

  #[Test]
  public function testInvokeSelectsDueSchedulesAndCreatesADraftWithOneWorkItemPerEquipment(): void
  {
    $dueBefore = new DateTimeImmutable('2026-02-01T00:00:00+00:00');

    $scheduleA = $this->makeSchedule('equipment-a');
    $scheduleB = $this->makeSchedule('equipment-b');

    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->expects(self::once())
      ->method('listDueForCampaign')
      ->with(self::ORG_ID, 'facility-1', 'fire_extinguisher', $dueBefore)
      ->willReturn([$scheduleA, $scheduleB]);

    $draftFactory = $this->createMock(InterventionDraftFactoryPort::class);
    $draftFactory->expects(self::once())
      ->method('create')
      ->with(self::callback(function (CreateInterventionDraftRequest $request): bool {
        self::assertSame(self::ORG_ID, $request->organizationId);
        self::assertSame('inspection_campaign', $request->type);
        self::assertSame('maintenance:campaign', $request->origin);
        self::assertSame(self::USER_ID, $request->actorUserId);
        self::assertCount(2, $request->workItems);
        self::assertSame('inspection', $request->workItems[0]->action);
        self::assertSame('{"equipmentId":"equipment-a"}', $request->workItems[0]->target);
        self::assertSame('{"equipmentId":"equipment-b"}', $request->workItems[1]->target);

        return true;
      }))
      ->willReturn(new CreatedInterventionDraft('intervention-1', 42, 2));

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with(self::USER_ID, self::ORG_ID, ['organization.maintenance.manage', 'organization.interventions.plan']);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = new GenerateInspectionCampaignHandler($schedules, $draftFactory, $authorization, $eventDispatcher);

    $result = $handler->__invoke(new GenerateInspectionCampaignCommand(
      organizationId: self::ORG_ID,
      actorUserId: self::USER_ID,
      name: 'Q1 Campaign',
      facilityId: 'facility-1',
      equipmentType: 'fire_extinguisher',
      dueBefore: $dueBefore,
    ));

    self::assertInstanceOf(GenerateInspectionCampaignResult::class, $result);
    self::assertSame('intervention-1', $result->interventionId);
    self::assertSame(42, $result->number);
    self::assertSame(2, $result->workItemsCount);
  }

  #[Test]
  public function testInvokeThrowsWhenNoScheduleMatchesTheFilters(): void
  {
    $schedules = $this->createStub(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('listDueForCampaign')->willReturn([]);

    $draftFactory = $this->createMock(InterventionDraftFactoryPort::class);
    $draftFactory->expects(self::never())->method('create');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new GenerateInspectionCampaignHandler($schedules, $draftFactory, $authorization, $eventDispatcher);

    $this->expectException(MaintenanceValidationException::class);

    $handler->__invoke(new GenerateInspectionCampaignCommand(
      organizationId: self::ORG_ID,
      actorUserId: self::USER_ID,
      name: 'Empty Campaign',
      facilityId: null,
      equipmentType: null,
      dueBefore: new DateTimeImmutable(),
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAPermissionIsMissing(): void
  {
    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->expects(self::never())->method('listDueForCampaign');

    $draftFactory = $this->createMock(InterventionDraftFactoryPort::class);
    $draftFactory->expects(self::never())->method('create');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')
      ->willThrowException(new OrganizationAccessDeniedException('Missing permission.'));

    $eventDispatcher = $this->createStub(EventDispatcherPort::class);

    $handler = new GenerateInspectionCampaignHandler($schedules, $draftFactory, $authorization, $eventDispatcher);

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new GenerateInspectionCampaignCommand(
      organizationId: self::ORG_ID,
      actorUserId: self::USER_ID,
      name: 'Q1 Campaign',
      facilityId: null,
      equipmentType: null,
      dueBefore: new DateTimeImmutable(),
    ));
  }

  private function makeSchedule(string $equipmentId): MaintenanceScheduleView
  {
    return new MaintenanceScheduleView(
      id: 'schedule-' . $equipmentId,
      organizationId: self::ORG_ID,
      equipmentId: $equipmentId,
      facilityId: 'facility-1',
      equipmentType: 'fire_extinguisher',
      intervalOverride: null,
      lastInspectionClosedAt: new DateTimeImmutable('2025-10-01'),
      nextDueAt: new DateTimeImmutable('2026-01-01'),
      dueStatus: 'overdue',
      lastRemindedAt: null,
      remindedFor: null,
      createdAt: new DateTimeImmutable('2025-10-01'),
      updatedAt: new DateTimeImmutable('2025-10-01'),
    );
  }
}
