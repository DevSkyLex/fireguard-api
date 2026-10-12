<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Infrastructure\Adapter\Event;

use DateTimeImmutable;
use Equipment\Application\Contract\Event\Equipment\{EquipmentCommissionedEvent, EquipmentDecommissionedEvent, EquipmentPutUnderMaintenanceEvent, EquipmentReturnedToStockEvent};
use Facility\Application\Contract\Event\Facility\{FacilityArchivedEvent, FacilityCreatedEvent, FacilityRestoredEvent, FacilityUpdatedEvent};
use Inspection\Application\Contract\Event\Inspection\{InspectionClosedEvent, InspectionSubmittedEvent};
use Inspection\Application\Contract\Event\NonConformity\{NonConformityRecordedEvent, NonConformityStatusChangedEvent};
use Intervention\Application\Contract\Event\Publication\InterventionPublishedEvent;
use Maintenance\Application\Contract\Event\Campaign\MaintenanceCampaignGeneratedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\{LoggerInterface, NullLogger};
use RuntimeException;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use Webhook\Application\UseCase\Command\Delivery\DispatchWebhookEvent\DispatchWebhookEventCommand;
use Webhook\Infrastructure\Adapter\Event\WebhookEventPublisherAdapter;

use function array_keys;
use function array_map;

/**
 * Test WebhookEventPublisherAdapterTest.
 *
 * @category Subscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookEventPublisherAdapter::class)]
final class WebhookEventPublisherAdapterTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  #[Test]
  public function publishesANewContributorProjectionWithDurableIdentity(): void
  {
    $source = $this->createStub(\Webhook\Application\Port\Outbound\WebhookEventSourcePort::class);
    $source->method('descriptors')->willReturn([
      new \Webhook\Application\Contract\Event\WebhookEventDescriptor('invoice.issued', 'Invoice issued', 'invoice.issued_event'),
    ]);
    $catalog = new \Webhook\Application\Service\WebhookEventCatalogRegistry([$source]);
    self::assertTrue($catalog->isAllowedEventType('invoice.issued'));
    $eventContext = $this->createStub(\Shared\Application\Port\Outbound\DurableEventContextPort::class);
    $eventContext->method('eventId')->willReturn('durable-event-id');
    $uuid = $this->createMock(UuidGeneratorPort::class);
    $uuid->expects(self::never())->method('generate');
    $now = new DateTimeImmutable('2026-10-11T12:00:00+00:00');
    $bus = $this->createMock(MessageBusInterface::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static function (DispatchWebhookEventCommand $command) use ($now): bool {
      self::assertSame('invoice.issued', $command->eventType);
      self::assertSame('durable-event-id', $command->eventId);
      self::assertSame(['invoiceId' => 'invoice-1'], $command->data);
      self::assertSame($now, $command->occurredAt);

      return true;
    }))->willReturn($this->envelope());

    new WebhookEventPublisherAdapter($bus, $uuid, new NullLogger(), $eventContext)->publish(
      new \Webhook\Application\Contract\Event\WebhookEventProjection(
        self::ORGANIZATION_ID,
        new \Webhook\Application\Contract\Event\WebhookEventType('invoice.issued'),
        ['invoiceId' => 'invoice-1'],
        $now,
      ),
    );
  }

  #[Test]
  public function itDispatchesADispatchWebhookEventCommandForANonConformityRecorded(): void
  {
    $messageBus = $this->createMock(MessageBusInterface::class);
    $messageBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(function (DispatchWebhookEventCommand $command): bool {
        self::assertSame(self::ORGANIZATION_ID, $command->organizationId);
        self::assertSame('inspection.non_conformity_recorded', $command->eventType);
        self::assertSame('nc-1', $command->data['nonConformityId']);
        self::assertSame('inspection-1', $command->data['inspectionId']);
        self::assertSame('critical', $command->data['severity']);
        self::assertNotSame('', $command->eventId);

        return true;
      }))
      ->willReturn($this->envelope());

    $publisher = new WebhookEventPublisherAdapter(
      $messageBus,
      $this->uuidFactory(),
      new NullLogger(),
      eventContext: new \Shared\Infrastructure\Messaging\Outbox\DurableEventContext(),
    );

    new \Inspection\Infrastructure\Adapter\Webhook\InspectionWebhookEventSourceAdapter($publisher)->onEvent(new NonConformityRecordedEvent(
      organizationId: self::ORGANIZATION_ID,
      inspectionId: 'inspection-1',
      nonConformityId: 'nc-1',
      severity: 'critical',
    ), 'inspection.non_conformity_recorded_event');
  }

  #[Test]
  public function itDispatchesForInterventionPublishedWithOnlyPublicFields(): void
  {
    $messageBus = $this->createMock(MessageBusInterface::class);
    $messageBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(function (DispatchWebhookEventCommand $command): bool {
        self::assertSame('intervention.published', $command->eventType);
        self::assertSame(['interventionId', 'publicationId'], array_keys($command->data));

        return true;
      }))
      ->willReturn($this->envelope());

    $publisher = new WebhookEventPublisherAdapter(
      $messageBus,
      $this->uuidFactory(),
      new NullLogger(),
      eventContext: new \Shared\Infrastructure\Messaging\Outbox\DurableEventContext(),
    );

    new \Intervention\Infrastructure\Adapter\Webhook\InterventionWebhookEventSourceAdapter($publisher)->onEvent(new InterventionPublishedEvent(
      organizationId: self::ORGANIZATION_ID,
      interventionId: 'intervention-1',
      publicationId: 'publication-1',
    ), 'intervention.intervention_published_event');
  }

  #[Test]
  public function itDispatchesForFacilityArchived(): void
  {
    $messageBus = $this->createMock(MessageBusInterface::class);
    $messageBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (DispatchWebhookEventCommand $command): bool => 'facility.archived' === $command->eventType
        && 'facility-1' === $command->data['facilityId']))
      ->willReturn($this->envelope());

    $publisher = new WebhookEventPublisherAdapter(
      $messageBus,
      $this->uuidFactory(),
      new NullLogger(),
      eventContext: new \Shared\Infrastructure\Messaging\Outbox\DurableEventContext(),
    );

    new \Facility\Infrastructure\Adapter\Webhook\FacilityWebhookEventSourceAdapter($publisher)->onEvent(new FacilityArchivedEvent(
      organizationId: self::ORGANIZATION_ID,
      facilityId: 'facility-1',
    ), 'facility.facility_archived_event');
  }

  #[Test]
  public function itDispatchesForFacilityCreated(): void
  {
    $messageBus = $this->createMock(MessageBusInterface::class);
    $messageBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (DispatchWebhookEventCommand $command): bool => 'facility.created' === $command->eventType
        && 'facility-1' === $command->data['facilityId']))
      ->willReturn($this->envelope());

    $publisher = new WebhookEventPublisherAdapter(
      $messageBus,
      $this->uuidFactory(),
      new NullLogger(),
      eventContext: new \Shared\Infrastructure\Messaging\Outbox\DurableEventContext(),
    );

    new \Facility\Infrastructure\Adapter\Webhook\FacilityWebhookEventSourceAdapter($publisher)->onEvent(new FacilityCreatedEvent(
      organizationId: self::ORGANIZATION_ID,
      facilityId: 'facility-1',
    ), 'facility.facility_created_event');
  }

  #[Test]
  public function itDispatchesForFacilityUpdatedWithChangedFieldNamesOnly(): void
  {
    $messageBus = $this->createMock(MessageBusInterface::class);
    $messageBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (DispatchWebhookEventCommand $command): bool => 'facility.updated' === $command->eventType
        && 'facility-1' === $command->data['facilityId']
        && ['name', 'code'] === $command->data['changedFields']))
      ->willReturn($this->envelope());

    $publisher = new WebhookEventPublisherAdapter(
      $messageBus,
      $this->uuidFactory(),
      new NullLogger(),
      eventContext: new \Shared\Infrastructure\Messaging\Outbox\DurableEventContext(),
    );

    new \Facility\Infrastructure\Adapter\Webhook\FacilityWebhookEventSourceAdapter($publisher)->onEvent(new FacilityUpdatedEvent(
      organizationId: self::ORGANIZATION_ID,
      facilityId: 'facility-1',
      changedFields: ['name', 'code'],
    ), 'facility.facility_updated_event');
  }

  #[Test]
  public function itDispatchesForEquipmentCommissioned(): void
  {
    $messageBus = $this->createMock(MessageBusInterface::class);
    $messageBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (DispatchWebhookEventCommand $command): bool => 'equipment.commissioned' === $command->eventType
        && 'equipment-1' === $command->data['equipmentId']
        && 'facility-1' === $command->data['facilityId']
        && 'in_stock' === $command->data['previousStatus']))
      ->willReturn($this->envelope());

    $publisher = new WebhookEventPublisherAdapter(
      $messageBus,
      $this->uuidFactory(),
      new NullLogger(),
      eventContext: new \Shared\Infrastructure\Messaging\Outbox\DurableEventContext(),
    );

    new \Equipment\Infrastructure\Adapter\Webhook\EquipmentWebhookEventSourceAdapter($publisher)->onEvent(new EquipmentCommissionedEvent(
      organizationId: self::ORGANIZATION_ID,
      equipmentId: 'equipment-1',
      facilityId: 'facility-1',
      previousStatus: 'in_stock',
    ), 'equipment.equipment_commissioned_event');
  }

  #[Test]
  public function itSwallowsAndLogsAMessageBusFailureRatherThanPropagating(): void
  {
    $messageBus = $this->createStub(MessageBusInterface::class);
    $messageBus->method('dispatch')->willThrowException(new RuntimeException('transport unavailable'));

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())->method('error')->with('Failed to dispatch webhook event.');

    $publisher = new WebhookEventPublisherAdapter(
      $messageBus,
      $this->uuidFactory(),
      $logger,
      eventContext: new \Shared\Infrastructure\Messaging\Outbox\DurableEventContext(),
    );

    // Must not throw — a webhook delivery failure must never fail the
    // request that triggered the source domain event.
    new \Facility\Infrastructure\Adapter\Webhook\FacilityWebhookEventSourceAdapter($publisher)->onEvent(new FacilityArchivedEvent(
      organizationId: self::ORGANIZATION_ID,
      facilityId: 'facility-1',
    ), 'facility.facility_archived_event');
  }

  #[Test]
  public function itDispatchesForEveryRemainingCuratedEvent(): void
  {
    /** @var list<DispatchWebhookEventCommand> $dispatched */
    $dispatched = [];

    $messageBus = $this->createStub(MessageBusInterface::class);
    $messageBus->method('dispatch')->willReturnCallback(
      static function (DispatchWebhookEventCommand $command) use (&$dispatched): Envelope {
        $dispatched[] = $command;

        return new Envelope($command);
      },
    );

    $publisher = new WebhookEventPublisherAdapter(
      $messageBus,
      $this->uuidFactory(),
      new NullLogger(),
      eventContext: new \Shared\Infrastructure\Messaging\Outbox\DurableEventContext(),
    );

    new \Equipment\Infrastructure\Adapter\Webhook\EquipmentWebhookEventSourceAdapter($publisher)->onEvent(new EquipmentDecommissionedEvent(
      organizationId: self::ORGANIZATION_ID,
      equipmentId: 'equipment-1',
      previousStatus: 'in_service',
    ), 'equipment.equipment_decommissioned_event');
    new \Equipment\Infrastructure\Adapter\Webhook\EquipmentWebhookEventSourceAdapter($publisher)->onEvent(new EquipmentPutUnderMaintenanceEvent(
      organizationId: self::ORGANIZATION_ID,
      equipmentId: 'equipment-1',
      facilityId: 'facility-1',
      previousStatus: 'in_service',
    ), 'equipment.equipment_put_under_maintenance_event');
    new \Equipment\Infrastructure\Adapter\Webhook\EquipmentWebhookEventSourceAdapter($publisher)->onEvent(new EquipmentReturnedToStockEvent(
      organizationId: self::ORGANIZATION_ID,
      equipmentId: 'equipment-1',
      previousStatus: 'under_maintenance',
    ), 'equipment.equipment_returned_to_stock_event');
    new \Inspection\Infrastructure\Adapter\Webhook\InspectionWebhookEventSourceAdapter($publisher)->onEvent(new InspectionSubmittedEvent(
      organizationId: self::ORGANIZATION_ID,
      inspectionId: 'inspection-1',
      equipmentId: 'equipment-1',
      result: 'compliant',
    ), 'inspection.inspection_submitted_event');
    new \Inspection\Infrastructure\Adapter\Webhook\InspectionWebhookEventSourceAdapter($publisher)->onEvent(new InspectionClosedEvent(
      organizationId: self::ORGANIZATION_ID,
      inspectionId: 'inspection-1',
      equipmentId: 'equipment-1',
      result: 'non_compliant',
    ), 'inspection.inspection_closed_event');
    new \Inspection\Infrastructure\Adapter\Webhook\InspectionWebhookEventSourceAdapter($publisher)->onEvent(new NonConformityStatusChangedEvent(
      organizationId: self::ORGANIZATION_ID,
      inspectionId: 'inspection-1',
      nonConformityId: 'nc-1',
      previousStatus: 'open',
      status: 'resolved',
    ), 'inspection.non_conformity_status_changed_event');
    new \Maintenance\Infrastructure\Adapter\Webhook\MaintenanceWebhookEventSourceAdapter($publisher)->onEvent(new MaintenanceCampaignGeneratedEvent(
      organizationId: self::ORGANIZATION_ID,
      interventionId: 'intervention-1',
      workItemsCount: 7,
    ), 'maintenance.maintenance_campaign_generated_event');
    new \Facility\Infrastructure\Adapter\Webhook\FacilityWebhookEventSourceAdapter($publisher)->onEvent(new FacilityRestoredEvent(
      organizationId: self::ORGANIZATION_ID,
      facilityId: 'facility-1',
    ), 'facility.facility_restored_event');

    self::assertSame([
      'equipment.decommissioned',
      'equipment.under_maintenance',
      'equipment.returned_to_stock',
      'inspection.submitted',
      'inspection.closed',
      'inspection.non_conformity_status_changed',
      'maintenance.campaign_generated',
      'facility.restored',
    ], array_map(static fn (DispatchWebhookEventCommand $command): string => $command->eventType, $dispatched));

    self::assertSame(['equipmentId', 'previousStatus'], array_keys($dispatched[0]->data));
    self::assertSame(['equipmentId', 'facilityId', 'previousStatus'], array_keys($dispatched[1]->data));
    self::assertSame(['equipmentId', 'previousStatus'], array_keys($dispatched[2]->data));
    self::assertSame('compliant', $dispatched[3]->data['result']);
    self::assertSame('non_compliant', $dispatched[4]->data['result']);
    self::assertSame('open', $dispatched[5]->data['previousStatus']);
    self::assertSame('resolved', $dispatched[5]->data['status']);
    self::assertSame(7, $dispatched[6]->data['workItemsCount']);
    self::assertSame('facility-1', $dispatched[7]->data['facilityId']);

    foreach ($dispatched as $command) {
      self::assertSame(self::ORGANIZATION_ID, $command->organizationId);
      self::assertNotSame('', $command->eventId);
    }
  }

  /**
   * Method uuidFactory.
   *
   * @return UuidGeneratorPort a uuid factory generating deterministic-enough raw ids for assertions
   */
  private function uuidFactory(): UuidGeneratorPort
  {
    $generator = $this->createStub(UuidGeneratorPort::class);
    $generator->method('generate')->willReturn('018f0b68-6758-7a12-8a1d-3f0d97f64aff');

    return $generator;
  }

  /**
   * Method envelope.
   *
   * @return Envelope a minimal envelope satisfying MessageBusInterface::dispatch()'s return type
   */
  private function envelope(): Envelope
  {
    return new Envelope(new DispatchWebhookEventCommand(
      organizationId: self::ORGANIZATION_ID,
      eventType: 'facility.archived',
      eventId: 'evt-1',
      data: [],
      occurredAt: new DateTimeImmutable(),
    ));
  }
}
