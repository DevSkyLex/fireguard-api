<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\EventSubscriber;

use Equipment\Application\UseCase\Command\Equipment\RecordInterventionServiceHistory\RecordInterventionServiceHistoryCommand;
use Intervention\Domain\Event\Publication\InterventionPublishedEvent;
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

/**
 * Subscriber InterventionServiceHistorySubscriber.
 *
 * Reacts to `InterventionPublishedEvent` — the single audit point of the
 * intervention write path, positioned exactly like
 * {@see \Audit\Infrastructure\EventSubscriber\AuditEventSubscriber}, which
 * subscribes to the very same event — and turns it into a
 * `RecordInterventionServiceHistoryCommand` so every published equipment
 * change gains a completed, point-in-time service entry in its maintenance
 * log, dispatched through the command bus (sync — no async routing).
 *
 * Subscriber errors must never propagate: a failure here would otherwise
 * abort the whole published-event fan-out, breaking the audit ledger entry
 * emitted by the very same event, even though this service-history sync is
 * a best-effort side effect that can never fail or roll back the
 * publication itself.
 *
 * @category Subscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionServiceHistorySubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param LoggerInterface $logger the logger
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private LoggerInterface $logger,
  ) {
  }
  // #endregion

  /**
   * Method getSubscribedEvents.
   *
   * @since 1.0.0
   *
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'intervention.intervention_published_event' => 'onInterventionPublished',
    ];
  }

  /**
   * Method onInterventionPublished.
   *
   * @since 1.0.0
   *
   * @param InterventionPublishedEvent $event the domain event
   */
  public function onInterventionPublished(InterventionPublishedEvent $event): void
  {
    try {
      $this->commandBus->dispatch(new RecordInterventionServiceHistoryCommand(
        organizationId: $event->organizationId,
        interventionId: $event->interventionId,
        publicationId: $event->publicationId,
        occurredAt: $event->occurredAt,
      ));
    } catch (Throwable $exception) {
      $this->logger->error('Failed to record intervention service history', [
        'organization_id' => $event->organizationId,
        'intervention_id' => $event->interventionId,
        'publication_id' => $event->publicationId,
        'error' => $exception->getMessage(),
      ]);
    }
  }
}
