<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\EventSubscriber;

use Equipment\Application\Contract\Event\EquipmentChangedEvent;
use Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort;
use Maintenance\Application\Service\MaintenanceScheduleService;
use Organization\Application\Contract\Event\OrganizationSettingsUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function count;
use function in_array;

/** Recomputes from current source state; repeated and delayed deliveries are harmless. */
final readonly class MaintenanceRecomputeSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Provides schedule recomputation and paged equipment lookup for source-state changes.
   *
   * @access public
   *
   * @param MaintenanceScheduleService $schedules refreshes schedules from current source state
   * @param MaintenanceEquipmentDirectoryPort $directory pages equipment after policy changes
   *
   * @return void
   */
  public function __construct(private MaintenanceScheduleService $schedules, private MaintenanceEquipmentDirectoryPort $directory)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * Maps source events to schedule refresh handlers.
   *
   * @access public
   *
   * @return array<string, string> event names and their handler methods
   */
  public static function getSubscribedEvents(): array
  {
    return ['equipment.equipment_changed_event' => 'equipmentChanged', 'organization.organization_settings_updated_event' => 'policyChanged'];
  }

  /**
   * Method equipmentChanged.
   *
   * Refreshes one schedule from the current equipment state.
   *
   * @access public
   *
   * @param EquipmentChangedEvent $event equipment and organization identifiers
   *
   * @return void
   */
  public function equipmentChanged(EquipmentChangedEvent $event): void
  {
    $this->schedules->refreshEquipment($event->organizationId, $event->equipmentId);
  }

  /**
   * Method policyChanged.
   *
   * Refreshes equipment schedules when the compliance policy changes.
   *
   * @access public
   *
   * @param OrganizationSettingsUpdatedEvent $event changed settings and organization scope
   *
   * @return void
   */
  public function policyChanged(OrganizationSettingsUpdatedEvent $event): void
  {
    if (!in_array('compliance', $event->changedFields, true)) {
      return;
    }
    $offset = 0;
    do {
      $page = $this->directory->listEquipmentPage(200, $offset, $event->organizationId);
      $this->schedules->refreshPage($page);
      $offset += 200;
    } while (200 === count($page));
  }
  // #endregion
}
