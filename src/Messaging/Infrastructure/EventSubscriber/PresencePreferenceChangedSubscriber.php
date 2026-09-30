<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\EventSubscriber;

use Messaging\Application\UseCase\Command\Presence\PublishPresencePreferenceChange\PublishPresencePreferenceChangeCommand;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\LoggerPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;
use User\Application\Contract\Presence\PresencePreferenceChangedEvent;

/**
 * Service PresencePreferenceChangedSubscriber.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PresencePreferenceChangedSubscriber implements EventSubscriberInterface
{
  /**
   * @since 1.0.0
   */
  public function __construct(private CommandBusPort $commandBus, private LoggerPort $logger)
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
      $this->commandBus->dispatch(new PublishPresencePreferenceChangeCommand($event->userId));
    } catch (Throwable) {
      $this->logger->warning('Presence organization fanout failed.', ['userId' => $event->userId]);
    }
  }
}
