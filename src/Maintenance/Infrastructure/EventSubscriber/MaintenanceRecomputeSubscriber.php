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
  public function __construct(private MaintenanceScheduleService $schedules, private MaintenanceEquipmentDirectoryPort $directory)
  {
  }

  public static function getSubscribedEvents(): array
  {
    return ['equipment.equipment_changed_event' => 'equipmentChanged', 'organization.organization_settings_updated_event' => 'policyChanged'];
  }

  public function equipmentChanged(EquipmentChangedEvent $event): void
  {
    $this->schedules->refreshEquipment($event->organizationId, $event->equipmentId);
  }

  public function policyChanged(OrganizationSettingsUpdatedEvent $event): void
  {
    if (!in_array('compliance', $event->changedFields, true)) {
      return;
    }
    $offset = 0;
    do {
      $page = $this->directory->listEquipmentPage(200, $offset, $event->organizationId);
      foreach ($page as $equipment) {
        $this->schedules->refreshEquipment($event->organizationId, $equipment->equipmentId);
      }
      $offset += 200;
    } while (200 === count($page));
  }
}
