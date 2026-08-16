<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\EventSubscriber;

use DateTimeImmutable;
use Equipment\Domain\Event\Equipment\{EquipmentCommissionedEvent, EquipmentDecommissionedEvent, EquipmentPutUnderMaintenanceEvent, EquipmentReturnedToStockEvent};
use Facility\Domain\Event\Facility\{FacilityArchivedEvent, FacilityCreatedEvent, FacilityRestoredEvent, FacilityUpdatedEvent};
use Inspection\Domain\Event\Inspection\{InspectionClosedEvent, InspectionSubmittedEvent};
use Inspection\Domain\Event\NonConformity\{NonConformityRecordedEvent, NonConformityStatusChangedEvent};
use Intervention\Domain\Event\Publication\InterventionPublishedEvent;
use Maintenance\Domain\Event\Campaign\MaintenanceCampaignGeneratedEvent;
use Psr\Log\LoggerInterface;
use Shared\Application\Factory\UuidFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;
use Webhook\Application\Contract\Event\{WebhookEventCatalog, WebhookEventType};
use Webhook\Application\UseCase\Command\Delivery\DispatchWebhookEvent\DispatchWebhookEventCommand;

/**
 * Subscriber WebhookEventSubscriber.
 *
 * Reacts to the CURATED allowlist of domain events dispatched through
 * `Shared\Application\Port\Outbound\EventDispatcherPort` (event name =
 * `<module>.<snake_case_class>`, positioned exactly like
 * {@see \Audit\Infrastructure\EventSubscriber\AuditEventSubscriber} and
 * {@see \Automation\Infrastructure\EventSubscriber\AutomationTriggerSubscriber},
 * which already subscribe to several of the very same events) and turns
 * each into a `DispatchWebhookEventCommand`, fire-and-forget dispatched
 * directly onto the raw Symfony message bus — NOT through `CommandBusPort`,
 * which expects a `HandledStamp` that an async-routed dispatch never
 * produces (see `Import\Infrastructure\Adapter\Messenger\MessengerImportJobQueueAdapter`'s
 * docblock for the same concern and precedent).
 *
 * This subscriber ONLY builds the stable public envelope `data` and
 * enqueues — it never looks up a subscription and never performs an
 * outbound HTTP call in the request thread. Subscriber errors must never
 * propagate: a failure here would otherwise fail the request that
 * triggered the source domain event, even though webhook delivery is a
 * best-effort side effect of it.
 *
 * @category Subscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WebhookEventSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessageBusInterface $messageBus the raw Symfony message bus (routed to the `webhook` transport)
   * @param UuidFactory $uuidFactory the uuid factory, used to mint each dispatch's dedupe key
   * @param LoggerInterface $logger the logger
   */
  public function __construct(
    private MessageBusInterface $messageBus,
    private UuidFactory $uuidFactory,
    private LoggerInterface $logger,
  ) {
  }
  // #endregion

  /**
   * Method getSubscribedEvents.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
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
      WebhookEventCatalog::FACILITY_CREATED_EVENT => 'onFacilityCreated',
      WebhookEventCatalog::FACILITY_ARCHIVED_EVENT => 'onFacilityArchived',
      WebhookEventCatalog::FACILITY_RESTORED_EVENT => 'onFacilityRestored',
      WebhookEventCatalog::FACILITY_UPDATED_EVENT => 'onFacilityUpdated',
    ];
  }

  /**
   * Method onEquipmentCommissioned.
   *
   * @since 1.0.0
   *
   * @param EquipmentCommissionedEvent $event the domain event
   */
  public function onEquipmentCommissioned(EquipmentCommissionedEvent $event): void
  {
    $this->enqueue($event->organizationId, WebhookEventType::EQUIPMENT_COMMISSIONED, [
      'equipmentId' => $event->equipmentId,
      'facilityId' => $event->facilityId,
      'previousStatus' => $event->previousStatus,
    ], $event->occurredAt);
  }

  /**
   * Method onEquipmentDecommissioned.
   *
   * @since 1.0.0
   *
   * @param EquipmentDecommissionedEvent $event the domain event
   */
  public function onEquipmentDecommissioned(EquipmentDecommissionedEvent $event): void
  {
    $this->enqueue($event->organizationId, WebhookEventType::EQUIPMENT_DECOMMISSIONED, [
      'equipmentId' => $event->equipmentId,
      'previousStatus' => $event->previousStatus,
    ], $event->occurredAt);
  }

  /**
   * Method onEquipmentPutUnderMaintenance.
   *
   * @since 1.0.0
   *
   * @param EquipmentPutUnderMaintenanceEvent $event the domain event
   */
  public function onEquipmentPutUnderMaintenance(EquipmentPutUnderMaintenanceEvent $event): void
  {
    $this->enqueue($event->organizationId, WebhookEventType::EQUIPMENT_UNDER_MAINTENANCE, [
      'equipmentId' => $event->equipmentId,
      'facilityId' => $event->facilityId,
      'previousStatus' => $event->previousStatus,
    ], $event->occurredAt);
  }

  /**
   * Method onEquipmentReturnedToStock.
   *
   * @since 1.0.0
   *
   * @param EquipmentReturnedToStockEvent $event the domain event
   */
  public function onEquipmentReturnedToStock(EquipmentReturnedToStockEvent $event): void
  {
    $this->enqueue($event->organizationId, WebhookEventType::EQUIPMENT_RETURNED_TO_STOCK, [
      'equipmentId' => $event->equipmentId,
      'previousStatus' => $event->previousStatus,
    ], $event->occurredAt);
  }

  /**
   * Method onInspectionSubmitted.
   *
   * @since 1.0.0
   *
   * @param InspectionSubmittedEvent $event the domain event
   */
  public function onInspectionSubmitted(InspectionSubmittedEvent $event): void
  {
    $this->enqueue($event->organizationId, WebhookEventType::INSPECTION_SUBMITTED, [
      'inspectionId' => $event->inspectionId,
      'equipmentId' => $event->equipmentId,
      'result' => $event->result,
    ], $event->occurredAt);
  }

  /**
   * Method onInspectionClosed.
   *
   * @since 1.0.0
   *
   * @param InspectionClosedEvent $event the domain event
   */
  public function onInspectionClosed(InspectionClosedEvent $event): void
  {
    $this->enqueue($event->organizationId, WebhookEventType::INSPECTION_CLOSED, [
      'inspectionId' => $event->inspectionId,
      'equipmentId' => $event->equipmentId,
      'result' => $event->result,
    ], $event->occurredAt);
  }

  /**
   * Method onNonConformityRecorded.
   *
   * @since 1.0.0
   *
   * @param NonConformityRecordedEvent $event the domain event
   */
  public function onNonConformityRecorded(NonConformityRecordedEvent $event): void
  {
    $this->enqueue($event->organizationId, WebhookEventType::NON_CONFORMITY_RECORDED, [
      'inspectionId' => $event->inspectionId,
      'nonConformityId' => $event->nonConformityId,
      'severity' => $event->severity,
    ], $event->occurredAt);
  }

  /**
   * Method onNonConformityStatusChanged.
   *
   * @since 1.0.0
   *
   * @param NonConformityStatusChangedEvent $event the domain event
   */
  public function onNonConformityStatusChanged(NonConformityStatusChangedEvent $event): void
  {
    $this->enqueue($event->organizationId, WebhookEventType::NON_CONFORMITY_STATUS_CHANGED, [
      'inspectionId' => $event->inspectionId,
      'nonConformityId' => $event->nonConformityId,
      'previousStatus' => $event->previousStatus,
      'status' => $event->status,
    ], $event->occurredAt);
  }

  /**
   * Method onInterventionPublished.
   *
   * @since 1.0.0
   *
   * @param InterventionPublishedEvent $event the domain event
   */
  public function onInterventionPublished(InterventionPublishedEvent $event): void
  {
    $this->enqueue($event->organizationId, WebhookEventType::INTERVENTION_PUBLISHED, [
      'interventionId' => $event->interventionId,
      'publicationId' => $event->publicationId,
    ], $event->occurredAt);
  }

  /**
   * Method onMaintenanceCampaignGenerated.
   *
   * @since 1.0.0
   *
   * @param MaintenanceCampaignGeneratedEvent $event the domain event
   */
  public function onMaintenanceCampaignGenerated(MaintenanceCampaignGeneratedEvent $event): void
  {
    $this->enqueue($event->organizationId, WebhookEventType::MAINTENANCE_CAMPAIGN_GENERATED, [
      'interventionId' => $event->interventionId,
      'workItemsCount' => $event->workItemsCount,
    ], $event->occurredAt);
  }

  /**
   * Method onFacilityCreated.
   *
   * @since 1.0.0
   *
   * @param FacilityCreatedEvent $event the domain event
   */
  public function onFacilityCreated(FacilityCreatedEvent $event): void
  {
    $this->enqueue($event->organizationId, WebhookEventType::FACILITY_CREATED, [
      'facilityId' => $event->facilityId,
    ], $event->occurredAt);
  }

  /**
   * Method onFacilityArchived.
   *
   * @since 1.0.0
   *
   * @param FacilityArchivedEvent $event the domain event
   */
  public function onFacilityArchived(FacilityArchivedEvent $event): void
  {
    $this->enqueue($event->organizationId, WebhookEventType::FACILITY_ARCHIVED, [
      'facilityId' => $event->facilityId,
    ], $event->occurredAt);
  }

  /**
   * Method onFacilityRestored.
   *
   * @since 1.0.0
   *
   * @param FacilityRestoredEvent $event the domain event
   */
  public function onFacilityRestored(FacilityRestoredEvent $event): void
  {
    $this->enqueue($event->organizationId, WebhookEventType::FACILITY_RESTORED, [
      'facilityId' => $event->facilityId,
    ], $event->occurredAt);
  }

  /**
   * Method onFacilityUpdated.
   *
   * @since 1.0.0
   *
   * @param FacilityUpdatedEvent $event the domain event
   */
  public function onFacilityUpdated(FacilityUpdatedEvent $event): void
  {
    $this->enqueue($event->organizationId, WebhookEventType::FACILITY_UPDATED, [
      'facilityId' => $event->facilityId,
      'changedFields' => $event->changedFields,
    ], $event->occurredAt);
  }

  /**
   * Method enqueue.
   *
   * Builds and fire-and-forget dispatches a `DispatchWebhookEventCommand`,
   * swallowing and logging any failure. `eventId` is a fresh UUID minted
   * once per call — it becomes the fan-out's `(subscriptionId, eventId)`
   * dedupe key, which stays stable across any Messenger redelivery of this
   * exact `DispatchWebhookEventCommand` (the same serialized message,
   * retried), which is the only realistic duplicate-processing scenario
   * this idempotency guard needs to cover.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param WebhookEventType $type the public event type
   * @param array<string, mixed> $data the curated, PII-free event data
   * @param DateTimeImmutable $occurredAt when the source event occurred
   */
  private function enqueue(string $organizationId, WebhookEventType $type, array $data, DateTimeImmutable $occurredAt): void
  {
    try {
      $this->messageBus->dispatch(new DispatchWebhookEventCommand(
        organizationId: $organizationId,
        eventType: $type->value,
        eventId: $this->uuidFactory->generateRaw(),
        data: $data,
        occurredAt: $occurredAt,
      ));
    } catch (Throwable $exception) {
      $this->logger->error('Failed to dispatch webhook event.', [
        'event_type' => $type->value,
        'organization_id' => $organizationId,
        'error' => $exception->getMessage(),
      ]);
    }
  }
}
