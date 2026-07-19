<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Infrastructure\EventSubscriber;

use DateTimeImmutable;
use Equipment\Domain\Event\Equipment\EquipmentCommissionedEvent;
use Facility\Domain\Event\Facility\FacilityArchivedEvent;
use Inspection\Domain\Event\NonConformity\NonConformityRecordedEvent;
use Intervention\Domain\Event\Publication\InterventionPublishedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\{LoggerInterface, NullLogger};
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use Webhook\Application\Contract\Event\WebhookEventCatalog;
use Webhook\Application\UseCase\Command\Delivery\DispatchWebhookEvent\DispatchWebhookEventCommand;
use Webhook\Infrastructure\EventSubscriber\WebhookEventSubscriber;

use function array_keys;

/**
 * Test WebhookEventSubscriberTest.
 *
 * @category Subscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookEventSubscriber::class)]
final class WebhookEventSubscriberTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  #[Test]
  public function itSubscribesToExactlyTheCuratedAllowlist(): void
  {
    self::assertInstanceOf(EventSubscriberInterface::class, new WebhookEventSubscriber(
      $this->createStub(MessageBusInterface::class),
      $this->uuidFactory(),
      new NullLogger(),
    ));

    $subscribed = WebhookEventSubscriber::getSubscribedEvents();

    self::assertSame([
      WebhookEventCatalog::EQUIPMENT_COMMISSIONED_EVENT => 'onEquipmentCommissioned',
      WebhookEventCatalog::EQUIPMENT_DECOMMISSIONED_EVENT => 'onEquipmentDecommissioned',
      WebhookEventCatalog::EQUIPMENT_PUT_UNDER_MAINTENANCE_EVENT => 'onEquipmentPutUnderMaintenance',
      WebhookEventCatalog::EQUIPMENT_RETURNED_TO_STOCK_EVENT => 'onEquipmentReturnedToStock',
      WebhookEventCatalog::INSPECTION_SUBMITTED_EVENT => 'onInspectionSubmitted',
      WebhookEventCatalog::INSPECTION_CLOSED_EVENT => 'onInspectionClosed',
      WebhookEventCatalog::NON_CONFORMITY_RECORDED_EVENT => 'onNonConformityRecorded',
      WebhookEventCatalog::NON_CONFORMITY_STATUS_CHANGED_EVENT => 'onNonConformityStatusChanged',
      WebhookEventCatalog::INTERVENTION_PUBLISHED_EVENT => 'onInterventionPublished',
      WebhookEventCatalog::MAINTENANCE_CAMPAIGN_GENERATED_EVENT => 'onMaintenanceCampaignGenerated',
      WebhookEventCatalog::FACILITY_ARCHIVED_EVENT => 'onFacilityArchived',
      WebhookEventCatalog::FACILITY_RESTORED_EVENT => 'onFacilityRestored',
    ], $subscribed);

    // Exactly the same event names Audit already subscribes to (verified names).
    self::assertArrayHasKey('inspection.non_conformity_recorded_event', $subscribed);
    self::assertArrayHasKey('intervention.intervention_published_event', $subscribed);
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

    $subscriber = new WebhookEventSubscriber($messageBus, $this->uuidFactory(), new NullLogger());

    $subscriber->onNonConformityRecorded(new NonConformityRecordedEvent(
      organizationId: self::ORGANIZATION_ID,
      inspectionId: 'inspection-1',
      nonConformityId: 'nc-1',
      severity: 'critical',
    ));
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

    $subscriber = new WebhookEventSubscriber($messageBus, $this->uuidFactory(), new NullLogger());

    $subscriber->onInterventionPublished(new InterventionPublishedEvent(
      organizationId: self::ORGANIZATION_ID,
      interventionId: 'intervention-1',
      publicationId: 'publication-1',
    ));
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

    $subscriber = new WebhookEventSubscriber($messageBus, $this->uuidFactory(), new NullLogger());

    $subscriber->onFacilityArchived(new FacilityArchivedEvent(
      organizationId: self::ORGANIZATION_ID,
      facilityId: 'facility-1',
    ));
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

    $subscriber = new WebhookEventSubscriber($messageBus, $this->uuidFactory(), new NullLogger());

    $subscriber->onEquipmentCommissioned(new EquipmentCommissionedEvent(
      organizationId: self::ORGANIZATION_ID,
      equipmentId: 'equipment-1',
      facilityId: 'facility-1',
      previousStatus: 'in_stock',
    ));
  }

  #[Test]
  public function itSwallowsAndLogsAMessageBusFailureRatherThanPropagating(): void
  {
    $messageBus = $this->createStub(MessageBusInterface::class);
    $messageBus->method('dispatch')->willThrowException(new RuntimeException('transport unavailable'));

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())->method('error')->with('Failed to dispatch webhook event.');

    $subscriber = new WebhookEventSubscriber($messageBus, $this->uuidFactory(), $logger);

    // Must not throw — a webhook delivery failure must never fail the
    // request that triggered the source domain event.
    $subscriber->onFacilityArchived(new FacilityArchivedEvent(
      organizationId: self::ORGANIZATION_ID,
      facilityId: 'facility-1',
    ));
  }

  /**
   * Method uuidFactory.
   *
   * @return UuidFactory a uuid factory generating deterministic-enough raw ids for assertions
   */
  private function uuidFactory(): UuidFactory
  {
    $generator = $this->createStub(UuidGeneratorPort::class);
    $generator->method('generate')->willReturn('018f0b68-6758-7a12-8a1d-3f0d97f64aff');

    return new UuidFactory($generator);
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
