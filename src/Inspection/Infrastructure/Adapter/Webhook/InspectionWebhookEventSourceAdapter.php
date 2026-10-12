<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Webhook;

use Inspection\Application\Contract\Event\Inspection\{InspectionClosedEvent, InspectionSubmittedEvent};
use Inspection\Application\Contract\Event\NonConformity\{NonConformityRecordedEvent, NonConformityStatusChangedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webhook\Application\Contract\Event\WebhookEventSourceMappingTrait;
use Webhook\Application\Port\Outbound\WebhookEventSourcePort;

/**
 * Class InspectionWebhookEventSourceAdapter
 *
 * Contributes Inspection-owned public projections and subscribes only to those source events.
 *
 * @category Adapter
 */
final readonly class InspectionWebhookEventSourceAdapter implements WebhookEventSourcePort, EventSubscriberInterface
{
  use WebhookEventSourceMappingTrait;

  // #region Constants
  /**
   * Constant EVENTS
   *
   * @var array<string, array{class: class-string, type: string, fields: list<string>}>
   */
  private const array EVENTS = [
    'inspection.inspection_submitted_event' => ['class' => InspectionSubmittedEvent::class, 'type' => 'inspection.submitted', 'fields' => ['inspectionId', 'equipmentId', 'result']],
    'inspection.inspection_closed_event' => ['class' => InspectionClosedEvent::class, 'type' => 'inspection.closed', 'fields' => ['inspectionId', 'equipmentId', 'result']],
    'inspection.non_conformity_recorded_event' => ['class' => NonConformityRecordedEvent::class, 'type' => 'inspection.non_conformity_recorded', 'fields' => ['inspectionId', 'nonConformityId', 'severity']],
    'inspection.non_conformity_status_changed_event' => ['class' => NonConformityStatusChangedEvent::class, 'type' => 'inspection.non_conformity_status_changed', 'fields' => ['inspectionId', 'nonConformityId', 'previousStatus', 'status']],
  ];
  // #endregion

  // #region Methods
  /**
   * Method sourceDefinitions.
   *
   * @since 1.0.0
   *
   * @return array<string, array{class: class-string, type: string, fields: list<string>}> the owner-selected public event mappings
   */
  protected static function sourceDefinitions(): array
  {
    return self::EVENTS;
  }
  // #endregion
}
