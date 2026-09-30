<?php

declare(strict_types=1);

namespace User\Infrastructure\EventSubscriber;

use Shared\Application\Port\Outbound\LoggerPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;
use User\Application\Contract\Presence\PresencePreferenceChangedEvent;
use User\Application\Port\Outbound\PresencePreferenceRealtimePort;

/**
 * Service PresencePreferenceRealtimeSubscriber.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PresencePreferenceRealtimeSubscriber implements EventSubscriberInterface
{
  /**
   * @since 1.0.0
   */
  public function __construct(private PresencePreferenceRealtimePort $realtime, private LoggerPort $logger)
  {
  }

  /**
   * @since 1.0.0
   */
  public static function getSubscribedEvents(): array
  {
    return ['user.presence_preference_changed_event' => 'onChanged'];
  }

  /**
   * @since 1.0.0
   */
  public function onChanged(PresencePreferenceChangedEvent $event): void
  {
    try {
      $this->realtime->publish($event->userId, $event->doNotDisturb, $event->revision, $event->invisible);
    } catch (Throwable) {
      $this->logger->warning('Presence preference Mercure publication failed.', ['userId' => $event->userId]);
    }
  }
}
