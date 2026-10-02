<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\EventSubscriber;

use Intervention\Application\Service\{InterventionNotificationService, InterventionRecurrenceNotifier};
use Intervention\Domain\Event\Recurrence\InterventionRecurrenceMaterializedEvent;
use Intervention\Domain\Event\Workflow\InterventionWorkflowNotificationRequestedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class InterventionWorkflowNotificationSubscriber
 *
 * Delivers committed workflow and recurrence consequences from the durable outbox.
 *
 * @category Subscriber
 */
final readonly class InterventionWorkflowNotificationSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param InterventionNotificationService $notifications workflow notification service
   * @param InterventionRecurrenceNotifier $recurrences recurrence failure notification service
   *
   * @return void
   */
  public function __construct(private InterventionNotificationService $notifications, private InterventionRecurrenceNotifier $recurrences)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * @access public
   *
   * @return array<string, string> committed events handled by this subscriber
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'intervention.intervention_workflow_notification_requested_event' => 'onWorkflow',
      'intervention.intervention_recurrence_materialized_event' => 'onRecurrence',
    ];
  }

  /**
   * Method onWorkflow
   *
   * @access public
   *
   * @param InterventionWorkflowNotificationRequestedEvent $event committed notification request
   *
   * @return void
   */
  public function onWorkflow(InterventionWorkflowNotificationRequestedEvent $event): void
  {
    match ($event->kind) {
      'assigned' => null === $event->memberId ? null : $this->notifications->assigned($event->interventionId, $event->interventionName, $event->memberId),
      'changes_requested' => $this->notifications->changesRequested($event->interventionId, $event->interventionName, $event->memberId),
      'submitted' => $this->notifications->submitted($event->interventionId, $event->interventionName, $event->organizationId ?? '', $event->actorUserId ?? ''),
      default => null,
    };
  }

  /**
   * Method onRecurrence
   *
   * @access public
   *
   * @param InterventionRecurrenceMaterializedEvent $event committed recurrence outcome
   *
   * @return void
   */
  public function onRecurrence(InterventionRecurrenceMaterializedEvent $event): void
  {
    if (!$event->succeeded && null !== $event->templateId) {
      $this->recurrences->notifyMaterializationFailed($event->organizationId, $event->recurrenceId, $event->templateId, $event->responsibleId, $event->error ?? 'Materialization failed.');
    }
  }
  // #endregion
}
