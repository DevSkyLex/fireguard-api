<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan;

use Closure;
use DateTimeImmutable;
use Intervention\Application\Contract\Draft\{CreateInterventionDraftRequest, CreatedInterventionDraft, InterventionMaintenanceWork};
use Intervention\Application\Port\Inbound\{InterventionDraftFactoryPort, InterventionMaintenanceWorkPort};
use Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy;
use Maintenance\Application\Contract\Directory\TrackableEquipment;
use Maintenance\Application\Contract\Plan\{MaintenanceOccurrenceState, MaintenanceOperationResult, MaintenancePlanState};
use Maintenance\Application\Contract\Schedule\{MaintenanceSchedulePage, MaintenanceScheduleSnapshot, MaintenanceScheduleView};
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Directory\{MaintenanceEquipmentDirectoryPort, MaintenanceFacilityLifecyclePort};
use Maintenance\Application\Port\Outbound\Plan\MaintenancePlanStorePort;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Application\Service\MaintenanceLegacyPlanService;
use Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\{ManageMaintenancePlanCommand, ManageMaintenancePlanHandler};
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceNotFoundException, MaintenanceValidationException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\MockObject\{MockObject, Stub};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, UuidGeneratorPort};
use Throwable;

use const DATE_ATOM;

/** Exercises orchestration contracts with every external port mocked. */
final class ManageMaintenancePlanHandlerTest extends TestCase
{
  private const string ORG = '780e8400-e29b-41d4-a716-446655445001';

  private const string EQ = '780e8400-e29b-41d4-a716-446655445002';

  private const string PLAN = '780e8400-e29b-41d4-a716-446655445003';

  private const string OCCURRENCE = '780e8400-e29b-41d4-a716-446655445004';

  private const string WORK = '780e8400-e29b-41d4-a716-446655445005';

  private const string RESULT = '780e8400-e29b-41d4-a716-446655445006';

  private MaintenancePlanState $plan;

  private ?MaintenanceOccurrenceState $occurrence = null;

  private ?MaintenanceScheduleView $legacy = null;

  private OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED;

  private string $mode = 'plans';

  private ?string $workStatus = null;

  private ?TrackableEquipment $currentEquipment = null;

  private MaintenanceCompliancePolicy $currentPolicy;

  /**
   * @var (Closure(int):list<MaintenancePlanState>)|null
   */
  private ?Closure $planPages = null;

  private MaintenancePlanStorePort&MockObject $store;

  private MaintenanceScheduleRepositoryPort&MockObject $schedules;

  private InterventionDraftFactoryPort&MockObject $drafts;

  private InterventionMaintenanceWorkPort&MockObject $work;

  private EventDispatcherPort&MockObject $events;

  private MaintenanceFacilityLifecyclePort&Stub $facilities;

  private MaintenanceEquipmentDirectoryPort&Stub $equipment;

  private ManageMaintenancePlanHandler $handler;

  protected function setUp(): void
  {
    $now = new DateTimeImmutable('2026-10-06T00:00:00Z');
    $due = new DateTimeImmutable('2027-01-31T00:00:00Z');
    $this->plan = new MaintenancePlanState(self::PLAN, self::ORG, self::EQ, null, 'fire_extinguisher', 'Annual control', 'control', 'P1Y', 'fixed', $due, $due, true, null, null, null, $now, $now);
    $this->store = $this->createMock(MaintenancePlanStorePort::class);
    $this->store->expects(self::atMost(1))->method('synchronized')->willReturnCallback(static fn (string $organization, callable $work): mixed => $work());
    $this->store->method('engineMode')->willReturnCallback(fn (): string => $this->mode);
    $this->store->method('find')->willReturnCallback(fn (): MaintenancePlanState => $this->plan);
    $this->store->method('findByLegacySchedule')->willReturnCallback(fn (): ?MaintenancePlanState => null === $this->plan->legacyScheduleId ? null : $this->plan);
    $this->store->method('list')->willReturnCallback(function (string $organization, int $limit, int $offset, ?string $equipment = null, ?string $kind = null, ?string $search = null, bool $includeArchived = false): array {
      if (null !== $this->planPages) {
        return ($this->planPages)($offset);
      }

      return 0 !== $offset || (null !== $kind && $kind !== $this->plan->operationKind) || (!$includeArchived && null !== $this->plan->archivedAt) ? [] : [$this->plan];
    });
    $this->store->method('count')->willReturn(1);
    $this->store->method('save')->willReturnCallback(function (MaintenancePlanState $plan): void { $this->plan = $plan; });
    $this->store->method('openOccurrence')->willReturnCallback(fn (): ?MaintenanceOccurrenceState => null !== $this->occurrence && 'open' === $this->occurrence->status ? $this->occurrence : null);
    $this->store->method('findOccurrence')->willReturnCallback(fn (): ?MaintenanceOccurrenceState => $this->occurrence);
    $this->store->method('saveOccurrence')->willReturnCallback(function (MaintenanceOccurrenceState $occurrence): void { $this->occurrence = $occurrence; });
    $this->schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $this->schedules->expects(self::atMost(5))->method('findById')->willReturnCallback(fn (): ?MaintenanceScheduleView => $this->legacy);
    $this->schedules->method('findByOrganizationAndEquipment')->willReturnCallback(fn (): ?MaintenanceScheduleView => $this->legacy);
    $this->schedules->method('save')->willReturnCallback(static fn (MaintenanceScheduleSnapshot $snapshot): MaintenanceScheduleView => new MaintenanceScheduleView($snapshot->id ?? self::PLAN, $snapshot->organizationId, $snapshot->equipmentId, $snapshot->facilityId, $snapshot->equipmentType, $snapshot->intervalOverride, $snapshot->lastInspectionClosedAt, $snapshot->nextDueAt, $snapshot->dueStatus, $snapshot->lastRemindedAt, $snapshot->remindedFor, new DateTimeImmutable('2026-10-06Z'), new DateTimeImmutable('2026-10-06Z')));
    $this->schedules->method('list')->willReturnCallback(fn (): MaintenanceSchedulePage => new MaintenanceSchedulePage(null === $this->legacy ? [] : [$this->legacy], 1, 200, null === $this->legacy ? 0 : 1));
    $this->equipment = $this->createStub(MaintenanceEquipmentDirectoryPort::class);
    $this->currentEquipment = new TrackableEquipment(self::EQ, self::ORG, null, 'fire_extinguisher', 'operational');
    $this->equipment->method('findEquipment')->willReturnCallback($this->equipmentSnapshot(...));
    $this->facilities = $this->createStub(MaintenanceFacilityLifecyclePort::class);
    $policy = $this->createStub(MaintenanceCompliancePolicyPort::class);
    $this->currentPolicy = new MaintenanceCompliancePolicy(['fire_extinguisher' => 'P1Y'], 30);
    $policy->method('compliancePolicy')->willReturnCallback(fn (): MaintenanceCompliancePolicy => $this->currentPolicy);
    $this->drafts = $this->createMock(InterventionDraftFactoryPort::class);
    $this->drafts->expects(self::atMost(1))->method('create')->willReturn(new CreatedInterventionDraft(self::WORK, 42, 1));
    $this->work = $this->createMock(InterventionMaintenanceWorkPort::class);
    $this->work->expects(self::atMost(3))->method('status')->willReturnCallback(fn (): ?string => $this->workStatus);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturnCallback(fn (): OrganizationAccessDecision => $this->decision);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::OCCURRENCE);
    $this->events = $this->createMock(EventDispatcherPort::class);
    $this->events->expects(self::atMost(3))->method('dispatch');
    $legacy = new MaintenanceLegacyPlanService($this->store, $this->schedules, $policy, $this->work, $clock, $ids);
    $this->handler = new ManageMaintenancePlanHandler($this->store, $this->schedules, $this->equipment, $this->facilities, $policy, $this->drafts, $this->work, $authorization, $clock, $ids, $this->events, $legacy);
  }

  #[Test]
  public function generationLinksTheOccurrenceWithoutCompletingOrMovingItsDueDate(): void
  {
    $due = $this->plan->nextDueAt?->format(DATE_ATOM);
    $this->drafts->expects(self::once())->method('create')->with(self::callback(function (CreateInterventionDraftRequest $request): bool {
      self::assertSame('inspection_campaign', $request->type);
      self::assertSame('/api/equipment/' . self::EQ, $request->workItems[0]->target);
      self::assertSame(self::PLAN, $request->workItems[0]->operationId);
      self::assertSame(self::OCCURRENCE, $request->workItems[0]->occurrenceId);
      self::assertSame('control', $request->workItems[0]->operationKind);
      self::assertSame('open', $this->occurrence?->status);

      return true;
    }))->willReturn(new CreatedInterventionDraft(self::WORK, 42, 1));
    $this->events->expects(self::once())->method('dispatch');
    $result = ($this->handler)(new ManageMaintenancePlanCommand('generate', self::ORG, 'actor', planId: self::PLAN));
    self::assertSame(self::OCCURRENCE, $result->occurrenceId);
    self::assertSame(self::WORK, $result->interventionId);
    self::assertSame(42, $result->number);
    self::assertSame(1, $this->occurrence?->attempt);
    self::assertSame('open', $this->occurrence->status);
    self::assertSame($due, $this->plan->nextDueAt?->format(DATE_ATOM));
  }

  #[Test]
  public function replayReturnsTheSameAttemptWithoutDraftOrEvent(): void
  {
    $this->attempt();
    $this->drafts->expects(self::never())->method('create');
    $this->events->expects(self::never())->method('dispatch');
    $result = ($this->handler)(new ManageMaintenancePlanCommand('generate', self::ORG, 'actor', planId: self::PLAN));
    self::assertTrue($result->replayed);
    self::assertSame(self::WORK, $result->interventionId);
    self::assertSame(42, $result->number);
  }

  #[Test]
  public function aSkippedPublishedTaskRequiresAnExplicitRetryOnTheSameOccurrence(): void
  {
    $this->attempt();
    self::assertNotNull($this->occurrence);
    $this->occurrence->interventionId = self::RESULT;
    $this->workStatus = 'published';
    $result = ($this->handler)(new ManageMaintenancePlanCommand('generate', self::ORG, 'actor', planId: self::PLAN, retry: true));
    $retried = $this->occurrence;
    self::assertSame(self::OCCURRENCE, $result->occurrenceId);
    self::assertSame(self::WORK, $result->interventionId);
    self::assertSame(2, $retried->attempt);
    self::assertSame('2027-01-31', $retried->dueAt->format('Y-m-d'));
    self::assertNull($retried->resultId);
  }

  #[Test]
  public function newMaintenancePlansStartPreparedWithoutAutomaticRegulatoryFrequency(): void
  {
    $this->store->expects(self::once())->method('save');
    $result = ($this->handler)(new ManageMaintenancePlanCommand('create', self::ORG, 'actor', equipmentId: self::EQ, name: 'Weekly servicing', operationKind: 'maintenance', interval: 'P1W', anchorAt: new DateTimeImmutable('2027-01-31T00:00:00Z')));
    self::assertFalse($result->details?->plan->active);
    self::assertSame('P1W', $result->details->plan->interval);
    self::assertSame('maintenance', $result->details->plan->operationKind);
  }

  #[Test]
  public function failedControlIsCompletedWhileItsDefectsRemainOutsideMaintenance(): void
  {
    $this->attempt();
    $result = $this->operationResult('failed');
    ($this->handler)(new ManageMaintenancePlanCommand('acknowledge_result', self::ORG, null, operationResult: $result));
    self::assertSame('completed', $this->occurrence?->status);
    self::assertSame(self::RESULT, $this->occurrence->resultId);
    self::assertSame('2028-01-31', $this->plan->nextDueAt?->format('Y-m-d'));
    self::assertSame('2026-10-06', $this->plan->lastCompletedAt?->format('Y-m-d'));
  }

  #[Test]
  public function unsuccessfulMaintenanceKeepsTheSameOccurrenceDueForAnExplicitRetry(): void
  {
    $this->plan->operationKind = 'maintenance';
    $this->attempt();
    $this->schedules->expects(self::never())->method('save');
    ($this->handler)(new ManageMaintenancePlanCommand('acknowledge_result', self::ORG, null, operationResult: $this->operationResult('failed')));
    self::assertSame('open', $this->occurrence?->status);
    self::assertSame('2027-01-31', $this->plan->nextDueAt?->format('Y-m-d'));
    self::assertNull($this->plan->lastCompletedAt);
  }

  #[Test]
  public function validationChecksDoNotAcknowledgeTheOccurrence(): void
  {
    $this->attempt();
    $this->store->expects(self::never())->method('saveOccurrence');
    $this->store->expects(self::never())->method('saveReceipt');
    ($this->handler)(new ManageMaintenancePlanCommand('validate_result', self::ORG, null, operationResult: $this->operationResult('passed')));
    self::assertNull($this->occurrence?->resultId);
  }

  #[Test]
  public function receiptReplayDoesNotMoveTheCalendarAgain(): void
  {
    $this->attempt();
    $this->store->method('hasReceipt')->willReturn(true);
    $this->store->expects(self::never())->method('saveOccurrence');
    $this->store->expects(self::never())->method('save');
    $this->events->expects(self::never())->method('dispatch');
    ($this->handler)(new ManageMaintenancePlanCommand('acknowledge_result', self::ORG, null, operationResult: $this->operationResult('passed')));
    self::assertSame('2027-01-31', $this->plan->nextDueAt?->format('Y-m-d'));
  }

  #[Test]
  public function resultForAnotherOperationIsRejected(): void
  {
    $this->attempt();
    $wrong = new MaintenanceOperationResult(self::ORG, self::EQ, self::OCCURRENCE, self::RESULT, self::WORK, 'maintenance', 'passed', new DateTimeImmutable('2026-10-06Z'));
    $this->store->expects(self::never())->method('saveReceipt');
    $this->expectException(MaintenanceValidationException::class);
    ($this->handler)(new ManageMaintenancePlanCommand('acknowledge_result', self::ORG, null, operationResult: $wrong));
  }

  #[Test]
  public function maintenanceNeverContributesToTheControlProjection(): void
  {
    $this->plan->operationKind = 'maintenance';
    $this->schedules->expects(self::once())->method('save')->with(self::callback(static function (MaintenanceScheduleSnapshot $snapshot): bool {
      self::assertSame('unscheduled', $snapshot->dueStatus);
      self::assertNull($snapshot->nextDueAt);
      self::assertNull($snapshot->lastInspectionClosedAt);

      return true;
    }))->willReturn(new MaintenanceScheduleView(self::PLAN, self::ORG, self::EQ, null, 'fire_extinguisher', null, null, null, 'unscheduled', null, null, new DateTimeImmutable('2026-10-06Z'), new DateTimeImmutable('2026-10-06Z')));
    ($this->handler)(new ManageMaintenancePlanCommand('refresh_projection', self::ORG, null, equipmentId: self::EQ));
  }

  #[Test]
  public function missingEquipmentSuspendsItsProjectionWithoutDeletingHistoryOrReminderEvidence(): void
  {
    $this->currentEquipment = null;
    $last = new DateTimeImmutable('2026-09-01Z');
    $due = new DateTimeImmutable('2027-03-01Z');
    $this->legacy = new MaintenanceScheduleView(self::PLAN, self::ORG, self::EQ, null, 'fire_extinguisher', 'P6M', $last, $due, 'up_to_date', $last, $due, $last, $last);
    $this->schedules->expects(self::never())->method('removeByOrganizationAndEquipment');
    $this->store->expects(self::never())->method('save');
    $this->store->expects(self::never())->method('saveOccurrence');
    $this->schedules->expects(self::once())->method('save')->with(self::callback(static function (MaintenanceScheduleSnapshot $snapshot) use ($last, $due): bool {
      self::assertSame('unscheduled', $snapshot->dueStatus);
      self::assertNull($snapshot->nextDueAt);
      self::assertSame($last, $snapshot->lastInspectionClosedAt);
      self::assertSame('P6M', $snapshot->intervalOverride);
      self::assertSame($last, $snapshot->lastRemindedAt);
      self::assertSame($due, $snapshot->remindedFor);

      return true;
    }))->willReturn($this->legacy);
    ($this->handler)(new ManageMaintenancePlanCommand('refresh_projection', self::ORG, null, equipmentId: self::EQ));
  }

  #[Test]
  public function preparePreservesHistoricalOverrideAndKnownDatesWithoutGenerating(): void
  {
    $this->mode = 'legacy';
    $date = new DateTimeImmutable('2026-09-01Z');
    $this->legacy = new MaintenanceScheduleView(self::PLAN, self::ORG, self::EQ, null, 'fire_extinguisher', 'P6M', $date, new DateTimeImmutable('2027-03-01Z'), 'up_to_date', null, null, $date, $date);
    $this->drafts->expects(self::never())->method('create');
    $result = ($this->handler)(new ManageMaintenancePlanCommand('prepare_legacy', self::ORG, 'actor'));
    self::assertSame(1, $result->preparedCount);
    self::assertSame('legacy', $this->plan->cadenceMode);
    self::assertSame('P6M', $this->plan->interval);
    self::assertSame('2027-03-01', $this->plan->nextDueAt?->format('Y-m-d'));
    self::assertFalse($this->plan->active);
  }

  #[Test]
  #[DataProvider('archivedHistoricalSources')]
  public function activationDoesNotReviveAnArchivedHistoricalCandidate(bool $cadenceRemoved): void
  {
    $this->mode = 'legacy';
    $date = new DateTimeImmutable('2026-09-01Z');
    $this->legacy = new MaintenanceScheduleView(self::PLAN, self::ORG, self::EQ, null, 'fire_extinguisher', $cadenceRemoved ? null : 'P6M', $date, new DateTimeImmutable('2027-03-01Z'), 'up_to_date', null, null, $date, $date);
    if ($cadenceRemoved) {
      $this->currentPolicy = new MaintenanceCompliancePolicy([], 30);
    }
    $this->plan->legacyScheduleId = self::PLAN;
    $this->plan->active = false;
    $this->plan->archivedAt = $date;
    $this->store->expects(self::once())->method('save');
    $this->store->expects(self::never())->method('saveOccurrence');
    $this->store->expects(self::once())->method('activateEngine');
    $this->work->expects(self::never())->method('attachOccurrence');
    $this->drafts->expects(self::never())->method('create');
    ($this->handler)(new ManageMaintenancePlanCommand('activate', self::ORG, 'actor'));
    self::assertSame(self::PLAN, $this->plan->id);
    self::assertSame($date, $this->plan->archivedAt);
    self::assertFalse($this->plan->active);
  }

  /**
   * @return iterable<string, array{bool}>
   */
  public static function archivedHistoricalSources(): iterable
  {
    yield 'historical override remains' => [false];
    yield 'historical default removed' => [true];
  }

  #[Test]
  public function aNameOnlyEditKeepsTheOpenOccurrenceAndItsCalendar(): void
  {
    $this->attempt();
    $due = $this->plan->nextDueAt;
    $anchor = $this->plan->anchorAt;
    $this->store->expects(self::never())->method('saveOccurrence');
    ($this->handler)(new ManageMaintenancePlanCommand('update', self::ORG, 'actor', planId: self::PLAN, name: 'Renamed control'));
    self::assertSame('Renamed control', $this->plan->name);
    self::assertSame($due, $this->plan->nextDueAt);
    self::assertSame($anchor, $this->plan->anchorAt);
    self::assertSame($due, $this->occurrence?->dueAt);
    self::assertSame(self::WORK, $this->occurrence?->interventionId);
  }

  #[Test]
  public function anOpenOccurrenceStillRejectsAnExplicitCalendarEdit(): void
  {
    $this->attempt();
    $this->store->expects(self::never())->method('save');
    $this->store->expects(self::never())->method('saveOccurrence');
    $this->expectException(MaintenanceValidationException::class);
    ($this->handler)(new ManageMaintenancePlanCommand('update', self::ORG, 'actor', planId: self::PLAN, nextDueOn: '2028-01-31'));
  }

  #[Test]
  public function aCampaignOmitsEquipmentThatMovedAwayFromTheSelectedFacility(): void
  {
    $this->plan->facilityId = 'old-facility';
    $this->plan->nextDueAt = new DateTimeImmutable('2026-10-01Z');
    $this->currentEquipment = new TrackableEquipment(self::EQ, self::ORG, 'current-facility', 'fire_extinguisher', 'operational');
    $this->store->expects(self::never())->method('saveOccurrence');
    $this->drafts->expects(self::never())->method('create');
    $this->expectException(MaintenanceValidationException::class);
    ($this->handler)(new ManageMaintenancePlanCommand('campaign', self::ORG, 'actor', facilityId: 'old-facility'));
  }

  #[Test]
  public function aCampaignUsesTheCurrentFacilityAndEquipmentType(): void
  {
    $this->plan->facilityId = 'old-facility';
    $this->plan->nextDueAt = new DateTimeImmutable('2026-10-01Z');
    $this->currentEquipment = new TrackableEquipment(self::EQ, self::ORG, 'current-facility', 'smoke_detector', 'operational');
    $this->store->expects(self::once())->method('save')->with(self::callback(static fn (MaintenancePlanState $plan): bool => 'current-facility' === $plan->facilityId && 'smoke_detector' === $plan->equipmentType));
    $result = ($this->handler)(new ManageMaintenancePlanCommand('campaign', self::ORG, 'actor', facilityId: 'current-facility', equipmentType: 'smoke_detector'));
    self::assertSame(self::WORK, $result->interventionId);
    self::assertSame(self::OCCURRENCE, $result->occurrenceId);
  }

  #[Test]
  public function aCampaignSkipsEquipmentOwnedByAnotherOrganization(): void
  {
    $this->plan->nextDueAt = new DateTimeImmutable('2026-10-01Z');
    $this->currentEquipment = new TrackableEquipment(self::EQ, self::RESULT, null, 'fire_extinguisher', 'operational');
    $this->store->expects(self::never())->method('saveOccurrence');
    $this->drafts->expects(self::never())->method('create');
    $this->expectException(MaintenanceValidationException::class);
    ($this->handler)(new ManageMaintenancePlanCommand('campaign', self::ORG, 'actor'));
  }

  #[Test]
  public function aCampaignSkipsALegacyOperationWhoseDefaultCadenceWasRemoved(): void
  {
    $this->historicalPlan();
    $this->currentPolicy = new MaintenanceCompliancePolicy([], 30);
    $this->store->expects(self::never())->method('saveOccurrence');
    $this->drafts->expects(self::never())->method('create');
    $this->expectException(MaintenanceValidationException::class);
    ($this->handler)(new ManageMaintenancePlanCommand('campaign', self::ORG, 'actor'));
  }

  #[Test]
  public function aCampaignUsesTheRefreshedCadenceBeforeItsDueCutoff(): void
  {
    $this->historicalPlan();
    $this->currentPolicy = new MaintenanceCompliancePolicy(['fire_extinguisher' => 'P2Y'], 30);
    $this->store->expects(self::never())->method('saveOccurrence');
    $this->drafts->expects(self::never())->method('create');
    $this->expectException(MaintenanceValidationException::class);
    ($this->handler)(new ManageMaintenancePlanCommand('campaign', self::ORG, 'actor', dueBefore: new DateTimeImmutable('2027-01-01Z')));
  }

  #[Test]
  public function aCampaignDefersCadenceSavesUntilAllDueOrderedPagesHaveBeenRead(): void
  {
    $lastPagePlan = clone $this->plan;
    $lastPagePlan->id = self::RESULT;
    $lastPagePlan->nextDueAt = new DateTimeImmutable('2026-10-01Z');
    $this->historicalPlan();
    $this->currentPolicy = new MaintenanceCompliancePolicy(['fire_extinguisher' => 'P2Y'], 30);
    $firstPage = [$this->plan];
    for ($index = 1; $index < 200; ++$index) {
      $inactive = clone $this->plan;
      $inactive->active = false;
      $firstPage[] = $inactive;
    }
    $this->planPages = static fn (int $offset): array => match ($offset) {
      0 => $firstPage,
      200 => [$lastPagePlan],
      default => [],
    };
    $this->store->expects(self::once())->method('save')->with(self::callback(static fn (MaintenancePlanState $plan): bool => self::RESULT === $plan->id));
    $result = ($this->handler)(new ManageMaintenancePlanCommand('campaign', self::ORG, 'actor'));
    $occurrence = $this->occurrence;
    self::assertSame(self::WORK, $result->interventionId);
    self::assertNotNull($occurrence);
    self::assertSame(self::RESULT, $occurrence->planId);
    self::assertSame('2026-10-01', $occurrence->dueAt->format('Y-m-d'));
  }

  #[Test]
  public function aCampaignRefreshesLegacyPolicyForTheCurrentEquipmentType(): void
  {
    $this->historicalPlan();
    $this->currentEquipment = new TrackableEquipment(self::EQ, self::ORG, null, 'smoke_detector', 'operational');
    $this->currentPolicy = new MaintenanceCompliancePolicy(['smoke_detector' => 'P6M'], 30);
    $result = ($this->handler)(new ManageMaintenancePlanCommand('campaign', self::ORG, 'actor', equipmentType: 'smoke_detector'));
    self::assertSame(self::WORK, $result->interventionId);
    self::assertSame('P6M', $this->plan->interval);
    self::assertSame('2026-04-01', $this->occurrence?->dueAt->format('Y-m-d'));
  }

  #[Test]
  public function aCampaignKeepsTheExplicitLegacyOverrideWhenTheDefaultWasRemoved(): void
  {
    $this->historicalPlan('P6M');
    $this->currentPolicy = new MaintenanceCompliancePolicy([], 30);
    $result = ($this->handler)(new ManageMaintenancePlanCommand('campaign', self::ORG, 'actor'));
    self::assertSame(self::WORK, $result->interventionId);
    self::assertSame('2026-04-01', $this->occurrence?->dueAt->format('Y-m-d'));
  }

  #[Test]
  public function ambiguousHistoricalWorkBlocksTheEngineSwitch(): void
  {
    $this->mode = 'legacy';
    $date = new DateTimeImmutable('2026-09-01Z');
    $this->legacy = new MaintenanceScheduleView(self::PLAN, self::ORG, self::EQ, null, 'fire_extinguisher', 'P6M', $date, new DateTimeImmutable('2027-03-01Z'), 'up_to_date', null, null, $date, $date);
    $this->work->method('findOpenLegacyInspectionWork')->willReturn([self::EQ => [new InterventionMaintenanceWork(self::WORK, 42, self::RESULT), new InterventionMaintenanceWork(self::RESULT, 43, self::WORK)]]);
    $this->store->expects(self::never())->method('activateEngine');
    $this->work->expects(self::never())->method('attachOccurrence');
    $this->expectException(MaintenanceValidationException::class);
    ($this->handler)(new ManageMaintenancePlanCommand('activate', self::ORG, 'actor'));
  }

  #[Test]
  public function archivedSitesSuspendGeneration(): void
  {
    $this->facilities->method('isArchived')->willReturn(true);
    $this->drafts->expects(self::never())->method('create');
    $this->expectException(MaintenanceValidationException::class);
    ($this->handler)(new ManageMaintenancePlanCommand('generate', self::ORG, 'actor', planId: self::PLAN));
  }

  /**
   * @param class-string<Throwable> $exception
   */
  #[Test]
  #[DataProvider('denials')]
  public function authorizationDeniesBeforeReadingPlans(OrganizationAccessDecision $decision, string $exception): void
  {
    $this->decision = $decision;
    $this->store->expects(self::never())->method('find');
    $this->store->expects(self::never())->method('synchronized');
    $this->expectException($exception);
    ($this->handler)(new ManageMaintenancePlanCommand('generate', self::ORG, 'actor', planId: self::PLAN));
  }

  /**
   * @return iterable<string, array{OrganizationAccessDecision, class-string<Throwable>}>
   */
  public static function denials(): iterable
  {
    yield 'outside organization' => [OrganizationAccessDecision::OUTSIDE_SCOPE, MaintenanceNotFoundException::class];
    yield 'missing permission' => [OrganizationAccessDecision::MISSING_PERMISSION, MaintenanceAccessDeniedException::class];
  }

  private function equipmentSnapshot(): ?TrackableEquipment
  {
    return $this->currentEquipment;
  }

  private function historicalPlan(?string $override = null): void
  {
    $date = new DateTimeImmutable('2025-10-01Z');
    $due = new DateTimeImmutable('2026-10-01Z');
    $this->plan->cadenceMode = 'legacy';
    $this->plan->legacyScheduleId = self::PLAN;
    $this->plan->lastCompletedAt = $date;
    $this->plan->anchorAt = $due;
    $this->plan->nextDueAt = $due;
    $this->legacy = new MaintenanceScheduleView(self::PLAN, self::ORG, self::EQ, null, 'fire_extinguisher', $override, $date, $due, 'overdue', null, null, $date, $date);
  }

  private function attempt(): void
  {
    $date = new DateTimeImmutable('2026-10-01Z');
    $this->occurrence = new MaintenanceOccurrenceState(self::OCCURRENCE, self::PLAN, self::ORG, $this->plan->nextDueAt ?? $date, 'open', 1, self::WORK, null, null, $date, $date, 42);
  }

  private function operationResult(string $outcome): MaintenanceOperationResult
  {
    return new MaintenanceOperationResult(self::ORG, self::EQ, self::OCCURRENCE, self::RESULT, self::WORK, $this->plan->operationKind, $outcome, new DateTimeImmutable('2026-10-06Z'), self::PLAN);
  }
}
