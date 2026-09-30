<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\EventSubscriber;

use Intervention\Application\Service\InterventionNotificationService;
use Intervention\Domain\Event\Publication\InterventionPublishedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Subscriber PublishedInterventionNotificationSubscriber. Runs only after durable publication. */
final readonly class PublishedInterventionNotificationSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the PublishedInterventionNotificationSubscriber dependencies and state.
   *
   * @access public
   *
   * @param InterventionNotificationService $notifications the notifications
   *
   * @return void
   */
  public function __construct(private InterventionNotificationService $notifications)
  {
  }

  // #endregion
  // #region Methods
  /**
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return ['intervention.intervention_published_event' => 'onPublished'];
  }

  /**
   * Method onPublished
   *
   * Handles published the supplied event.
   *
   * @access public
   *
   * @param InterventionPublishedEvent $event the event to handle
   *
   * @return void
   */
  public function onPublished(InterventionPublishedEvent $event): void
  {
    $this->notifications->published($event->interventionId, $event->interventionName, $event->recipientMemberIds);
  }
  // #endregion
}
