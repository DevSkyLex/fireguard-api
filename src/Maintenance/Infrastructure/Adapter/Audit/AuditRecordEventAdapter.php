<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Maintenance\Application\Contract\Event\Campaign\MaintenanceCampaignGeneratedEvent;
use Maintenance\Application\Contract\Event\Export\MaintenanceSchedulesExportedEvent;
use Maintenance\Application\Contract\Event\Schedule\MaintenanceScheduleOverriddenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Maintenance-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
 *
 * @category Adapter
 */
final readonly class AuditRecordEventAdapter implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the published durable recording seam.
   *
   * @access public
   *
   * @param AuditRecordingPort $audit generic Audit write capability
   *
   * @return void
   */
  public function __construct(private AuditRecordingPort $audit)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * Preserves the existing owner event names and audit action mapping.
   *
   * @access public
   *
   * @return array<string, string> owner event listeners
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'maintenance.maintenance_schedules_exported_event' => 'onMaintenanceSchedulesExported',
      'maintenance.maintenance_schedule_overridden_event' => 'onMaintenanceScheduleOverridden',
      'maintenance.maintenance_campaign_generated_event' => 'onMaintenanceCampaignGenerated',
    ];
  }

  /**
   * Method onMaintenanceSchedulesExported.
   *
   * Records every CSV export of an organization's maintenance schedules —
   * the maintenance module auditing its own export action, mirroring
   * {@see self::onInterventionsExported()}. Metadata carries only the
   * applied filter *names*, never their raw values.
   *
   * @since 1.6.0
   *
   * @param MaintenanceSchedulesExportedEvent $event the domain event
   */
  public function onMaintenanceSchedulesExported(MaintenanceSchedulesExportedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'maintenance.schedules_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onMaintenanceScheduleOverridden.
   *
   * Records a maintenance schedule interval override being set or cleared.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleOverriddenEvent $event the domain event
   */
  public function onMaintenanceScheduleOverridden(MaintenanceScheduleOverriddenEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'maintenance.schedule_overridden',
      organizationId: $event->organizationId,
      subjectType: 'maintenance_schedule',
      subjectId: $event->scheduleId,
      metadata: [
        'equipment_id' => $event->equipmentId,
        'interval_override' => $event->intervalOverride,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onMaintenanceCampaignGenerated.
   *
   * Records an inspection campaign generated from due/overdue maintenance
   * schedules.
   *
   * @since 1.0.0
   *
   * @param MaintenanceCampaignGeneratedEvent $event the domain event
   */
  public function onMaintenanceCampaignGenerated(MaintenanceCampaignGeneratedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'maintenance.campaign_generated',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: [
        'work_items_count' => $event->workItemsCount,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }
  // #endregion
}
