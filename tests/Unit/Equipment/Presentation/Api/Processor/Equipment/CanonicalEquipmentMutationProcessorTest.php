<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\{Delete, Patch};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Inbound\EquipmentMaintenanceLogSynchronizerPort;
use Equipment\Domain\Event\Equipment\{EquipmentCommissionedEvent, EquipmentDecommissionedEvent, EquipmentPutUnderMaintenanceEvent, EquipmentReturnedToStockEvent};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Presentation\Api\Dto\Input\Equipment\PatchCanonicalEquipmentInput;
use Equipment\Presentation\Api\Processor\Equipment\CanonicalEquipmentMutationProcessor;
use Equipment\Presentation\Api\Provider\Equipment\CanonicalEquipmentProvider;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[CoversClass(CanonicalEquipmentMutationProcessor::class)]
final class CanonicalEquipmentMutationProcessorTest extends TestCase
{
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440004';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440005';

  #[Test]
  public function testDeletingPublishedEquipmentDecommissionsIt(): void
  {
    $record = $this->record();
    $record->interventionId = self::INTERVENTION_ID;

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('remove');
    $entityManager->expects(self::once())->method('flush');

    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress'),
    );
    $resources->expects(self::once())->method('touchDraftIntervention')->with(self::INTERVENTION_ID);

    $result = $this->processor(
      $record,
      $this->request('DELETE'),
      $entityManager,
      $resources,
    )->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);

    self::assertNull($result);
    self::assertSame('decommissioned', $record->status);
    self::assertSame(4, $record->revision);
  }

  #[Test]
  public function testDeletingAlreadyDecommissionedEquipmentIsIdempotent(): void
  {
    // A repeat DELETE must neither bump the revision nor touch the maintenance
    // log, matching the facility and inspection canonical surfaces.
    $record = $this->record();
    $record->status = 'decommissioned';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('remove');
    $entityManager->expects(self::once())->method('flush');

    $synchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $synchronizer->expects(self::never())->method('syncForStatusTransition');

    $result = $this->processor(
      $record,
      $this->request('DELETE'),
      $entityManager,
      null,
      $synchronizer,
    )->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);

    self::assertNull($result);
    self::assertSame('decommissioned', $record->status);
    self::assertSame(3, $record->revision);
  }

  #[Test]
  public function testMergePatchExplicitNullClearsNullableField(): void
  {
    $record = $this->record();
    $record->serialNumber = 'SN-123';
    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $result = $this->processor(
      $record,
      $this->request('PATCH', '{"serialNumber":null}'),
      $entityManager,
    )->process(new PatchCanonicalEquipmentInput(), new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertNull($record->serialNumber);
    self::assertSame(4, $record->revision);
    self::assertSame(4, $result?->revision);
  }

  #[Test]
  public function testRejectsIllegalPublishedStatusTransition(): void
  {
    $record = $this->record();
    $record->status = 'decommissioned';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('flush');

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"operational"}'),
      $entityManager,
    )->process($this->patchStatus('operational'), new Patch(), ['id' => self::EQUIPMENT_ID]);
  }

  #[Test]
  public function testAllowsLegalPublishedStatusTransition(): void
  {
    $record = $this->record();

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $result = $this->processor(
      $record,
      $this->request('PATCH', '{"status":"under_maintenance"}'),
      $entityManager,
    )->process($this->patchStatus('under_maintenance'), new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertSame('under_maintenance', $record->status);
    self::assertSame(4, $record->revision);
    self::assertSame(4, $result?->revision);
  }

  #[Test]
  public function testCommissioningStampsCommissionedAtAndSyncsLog(): void
  {
    $record = $this->record();
    $record->status = 'in_stock';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $synchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $synchronizer->expects(self::once())
      ->method('syncForStatusTransition')
      ->with(self::EQUIPMENT_ID, self::ORGANIZATION_ID, 'in_stock', 'operational');

    self::assertNull($record->commissionedAt);

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"operational"}'),
      $entityManager,
      null,
      $synchronizer,
    )->process($this->patchStatus('operational'), new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertSame('operational', $record->status);
    self::assertInstanceOf(DateTimeImmutable::class, $record->commissionedAt);
  }

  #[Test]
  public function testEnteringMaintenanceSyncsLog(): void
  {
    $record = $this->record();

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $synchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $synchronizer->expects(self::once())
      ->method('syncForStatusTransition')
      ->with(self::EQUIPMENT_ID, self::ORGANIZATION_ID, 'operational', 'under_maintenance');

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"under_maintenance"}'),
      $entityManager,
      null,
      $synchronizer,
    )->process($this->patchStatus('under_maintenance'), new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertSame('under_maintenance', $record->status);
  }

  #[Test]
  public function testDeleteClosesOpenMaintenanceLogOnDecommission(): void
  {
    $record = $this->record();
    $record->status = 'under_maintenance';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $synchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $synchronizer->expects(self::once())
      ->method('syncForStatusTransition')
      ->with(self::EQUIPMENT_ID, self::ORGANIZATION_ID, 'under_maintenance', 'decommissioned');

    $this->processor(
      $record,
      $this->request('DELETE'),
      $entityManager,
      null,
      $synchronizer,
    )->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);

    self::assertSame('decommissioned', $record->status);
  }

  #[Test]
  public function testDraftStatusChangeSkipsMaintenanceSyncAndCommissionedAt(): void
  {
    // Draft (intervention scratchpad) records are materialized at publication, so
    // they must not touch the real maintenance history or commissioning date.
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;
    $record->status = 'in_stock';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress'),
    );

    $synchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $synchronizer->expects(self::never())->method('syncForStatusTransition');

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"operational"}'),
      $entityManager,
      $resources,
      $synchronizer,
    )->process($this->patchStatus('operational'), new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertSame('operational', $record->status);
    self::assertNull($record->commissionedAt);
  }

  #[Test]
  public function testRejectsClearingFacilityOfUnderMaintenanceEquipment(): void
  {
    // Clearing the facility of an in-service asset would strand it in an illegal
    // facility-less state and leak its open maintenance log; the caller must move
    // it back to stock first.
    $record = $this->record();
    $record->status = 'under_maintenance';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('flush');

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->processor(
      $record,
      $this->request('PATCH', '{"facility":null}'),
      $entityManager,
    )->process(new PatchCanonicalEquipmentInput(), new Patch(), ['id' => self::EQUIPMENT_ID]);
  }

  #[Test]
  public function testDraftRecordSkipsStatusTransitionValidation(): void
  {
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;
    $record->status = 'decommissioned';
    $record->facilityId = null;

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress'),
    );

    // A draft (intervention scratchpad) record may be freely edited — an
    // otherwise-illegal transition (decommissioned -> in_stock) must not throw.
    $result = $this->processor(
      $record,
      $this->request('PATCH', '{"status":"in_stock"}'),
      $entityManager,
      $resources,
    )->process($this->patchStatus('in_stock'), new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertSame('in_stock', $record->status);
    self::assertSame(4, $result?->revision);
  }

  #[Test]
  public function testDeletingPublishedEquipmentDispatchesDecommissionedEvent(): void
  {
    // Audit ledger: decommissioning a published asset through the canonical
    // DELETE emits an EquipmentDecommissionedEvent carrying the pre-retirement status.
    $record = $this->record();

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof EquipmentDecommissionedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::EQUIPMENT_ID === $event->equipmentId
        && 'operational' === $event->previousStatus,
    ));

    $result = $this->processor(
      $record,
      $this->request('DELETE'),
      eventDispatcher: $eventDispatcher,
    )->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);

    self::assertNull($result);
    self::assertSame('decommissioned', $record->status);
  }

  #[Test]
  public function testCommissioningPublishedEquipmentDispatchesCommissionedEvent(): void
  {
    // Audit ledger: a published in_stock -> operational PATCH emits an
    // EquipmentCommissionedEvent carrying the assigned facility.
    $record = $this->record();
    $record->status = 'in_stock';

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof EquipmentCommissionedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::EQUIPMENT_ID === $event->equipmentId
        && self::FACILITY_ID === $event->facilityId
        && 'in_stock' === $event->previousStatus,
    ));

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"operational"}'),
      eventDispatcher: $eventDispatcher,
    )->process($this->patchStatus('operational'), new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertSame('operational', $record->status);
  }

  #[Test]
  public function testEnteringMaintenanceDispatchesPutUnderMaintenanceEvent(): void
  {
    // Audit ledger: a published operational -> under_maintenance PATCH emits an
    // EquipmentPutUnderMaintenanceEvent carrying the assigned facility.
    $record = $this->record();

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof EquipmentPutUnderMaintenanceEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::EQUIPMENT_ID === $event->equipmentId
        && self::FACILITY_ID === $event->facilityId
        && 'operational' === $event->previousStatus,
    ));

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"under_maintenance"}'),
      eventDispatcher: $eventDispatcher,
    )->process($this->patchStatus('under_maintenance'), new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertSame('under_maintenance', $record->status);
  }

  #[Test]
  public function testReturningToStockDispatchesReturnedToStockEvent(): void
  {
    // Audit ledger: a published operational -> in_stock PATCH emits an
    // EquipmentReturnedToStockEvent — the canonical surface is the only
    // emitter of equipment.returned_to_stock.
    $record = $this->record();

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof EquipmentReturnedToStockEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::EQUIPMENT_ID === $event->equipmentId
        && 'operational' === $event->previousStatus,
    ));

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"in_stock"}'),
      eventDispatcher: $eventDispatcher,
    )->process($this->patchStatus('in_stock'), new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertSame('in_stock', $record->status);
  }

  #[Test]
  public function testDecommissioningViaPatchDispatchesDecommissionedEvent(): void
  {
    // Audit ledger: the PATCH route to the terminal state must emit exactly
    // like the canonical DELETE.
    $record = $this->record();

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof EquipmentDecommissionedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::EQUIPMENT_ID === $event->equipmentId
        && 'operational' === $event->previousStatus,
    ));

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"decommissioned"}'),
      eventDispatcher: $eventDispatcher,
    )->process($this->patchStatus('decommissioned'), new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertSame('decommissioned', $record->status);
  }

  #[Test]
  public function testRepeatDeleteOnDecommissionedEquipmentDispatchesNothing(): void
  {
    // The idempotent repeat DELETE leaves the record untouched: no ledger row.
    $record = $this->record();
    $record->status = 'decommissioned';

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->processor(
      $record,
      $this->request('DELETE'),
      eventDispatcher: $eventDispatcher,
    )->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);
  }

  #[Test]
  public function testDeletingDraftEquipmentDispatchesNothing(): void
  {
    // A draft (intervention scratchpad) hard-delete never reaches the ledger.
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress'),
    );

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->processor(
      $record,
      $this->request('DELETE'),
      resources: $resources,
      eventDispatcher: $eventDispatcher,
    )->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);
  }

  #[Test]
  public function testPatchingDraftEquipmentDispatchesNothing(): void
  {
    // Draft scratchpad edits are not audited, even on a status change.
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;
    $record->status = 'in_stock';

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress'),
    );

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"operational"}'),
      resources: $resources,
      eventDispatcher: $eventDispatcher,
    )->process($this->patchStatus('operational'), new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertSame('operational', $record->status);
  }

  #[Test]
  public function testRolledBackMutationDispatchesNothing(): void
  {
    // The audit events are collected during the mutation but dispatched only
    // after the transaction commits: a commit failure (rollback) must leave
    // no ledger row — the ledger is append-only and hash-chained, so a
    // phantom entry could never be removed.
    $record = $this->record();

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static function (callable $callback): mixed {
        $callback();

        throw new RuntimeException('commit failed');
      },
    );
    $entityManager->method('find')->with(EquipmentRecord::class, self::EQUIPMENT_ID)->willReturn($record);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->expectException(RuntimeException::class);

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"under_maintenance"}'),
      $entityManager,
      eventDispatcher: $eventDispatcher,
    )->process($this->patchStatus('under_maintenance'), new Patch(), ['id' => self::EQUIPMENT_ID]);
  }

  private function processor(
    EquipmentRecord $record,
    RequestStack $requestStack,
    ?EntityManagerInterface $entityManager = null,
    ?InterventionResourceGatewayPort $resources = null,
    ?EquipmentMaintenanceLogSynchronizerPort $synchronizer = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): CanonicalEquipmentMutationProcessor {
    $entityManager ??= $this->entityManager($record);
    $resources ??= $this->createStub(InterventionResourceGatewayPort::class);
    $synchronizer ??= $this->createStub(EquipmentMaintenanceLogSynchronizerPort::class);
    $eventDispatcher ??= $this->createStub(EventDispatcherPort::class);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());
    $manager = new InterventionResourceManager($resources);
    $provider = new CanonicalEquipmentProvider(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      $manager,
    );

    return new CanonicalEquipmentMutationProcessor(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      $provider,
      $manager,
      new RevisionGuard($requestStack),
      new MergePatchFields($requestStack),
      $synchronizer,
      $eventDispatcher,
    );
  }

  /**
   * @return EntityManagerInterface&MockObject
   */
  private function entityManager(EquipmentRecord $record): EntityManagerInterface
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->with(EquipmentRecord::class, self::EQUIPMENT_ID)->willReturn($record);

    return $entityManager;
  }

  private function patchStatus(string $status): PatchCanonicalEquipmentInput
  {
    $input = new PatchCanonicalEquipmentInput();
    $input->status = $status;

    return $input;
  }

  private function record(): EquipmentRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $record = new EquipmentRecord();
    $record->id = self::EQUIPMENT_ID;
    $record->organization = $organization;
    $record->recordStatus = 'published';
    $record->revision = 3;
    $record->type = 'fire_extinguisher';
    $record->status = 'operational';
    $record->facilityId = self::FACILITY_ID;
    $record->createdAt = new DateTimeImmutable();
    $record->updatedAt = new DateTimeImmutable();

    return $record;
  }

  private function request(string $method, ?string $content = null): RequestStack
  {
    $request = Request::create('/api/equipment/' . self::EQUIPMENT_ID, $method, [], [], [], [], $content);
    $request->headers->set('If-Match', '"revision-3"');
    $stack = new RequestStack();
    $stack->push($request);

    return $stack;
  }

  private function user(): SecurityUser
  {
    return new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true);
  }
}
