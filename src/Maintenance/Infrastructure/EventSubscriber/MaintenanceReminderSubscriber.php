<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\EventSubscriber;

use Maintenance\Application\Service\MaintenanceReminderNotifier;
use Maintenance\Domain\Event\Reminder\MaintenanceReminderRequestedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Delivers committed reminder requests; transient failures reach the outbox retry policy.
 *
 * @category EventSubscriber
 */
final readonly class MaintenanceReminderSubscriber implements EventSubscriberInterface
{
  public function __construct(private MaintenanceReminderNotifier $notifier)
  {
  }

  /**
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return ['maintenance.maintenance_reminder_requested_event' => 'requested'];
  }

  /**
   * Delivers a request using stable per-recipient notification identities.
   */
  public function requested(MaintenanceReminderRequestedEvent $event): void
  {
    $this->notifier->remind($event->organizationId, $event->equipmentId, $event->facilityId, $event->nextDueAt, $event->overdue);
  }
}
