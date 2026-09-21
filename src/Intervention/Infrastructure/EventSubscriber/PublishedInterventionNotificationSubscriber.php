<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\EventSubscriber;

use Intervention\Application\Service\InterventionNotificationService;
use Intervention\Domain\Event\Publication\InterventionPublishedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Subscriber PublishedInterventionNotificationSubscriber. Runs only after durable publication. */
final readonly class PublishedInterventionNotificationSubscriber implements EventSubscriberInterface
{
  public function __construct(private InterventionNotificationService $notifications)
  {
  }

  /**
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return ['intervention.intervention_published_event' => 'onPublished'];
  }

  public function onPublished(InterventionPublishedEvent $event): void
  {
    $this->notifications->published($event->interventionId, $event->interventionName, $event->recipientMemberIds);
  }
}
