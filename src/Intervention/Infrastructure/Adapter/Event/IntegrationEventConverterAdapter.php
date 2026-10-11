<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Event;

use Shared\Application\Port\Outbound\Event\{IntegrationEventConverterPort, IntegrationEventSnapshotPort};
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Class IntegrationEventConverterAdapter
 *
 * Converts this owner's historical Domain snapshots into its published integration contracts.
 *
 * @category Adapter
 */
#[AutoconfigureTag('shared.integration_event_converter')]
final readonly class IntegrationEventConverterAdapter implements IntegrationEventConverterPort
{
  // #region Constants
  /**
   * Constant CONTRACTS
   *
   * Exact owner-local legacy classes supported by the event compatibility decoder.
   *
   * @var array<class-string, class-string>
   */
  private const array CONTRACTS = [
    'Intervention\Domain\Event\Export\InterventionsExportedEvent' => \Intervention\Application\Contract\Event\Export\InterventionsExportedEvent::class,
    'Intervention\Domain\Event\InterventionReportExportedEvent' => \Intervention\Application\Contract\Event\InterventionReportExportedEvent::class,
    'Intervention\Domain\Event\Publication\InterventionPublicationFailedEvent' => \Intervention\Application\Contract\Event\Publication\InterventionPublicationFailedEvent::class,
    'Intervention\Domain\Event\Publication\InterventionPublishedEvent' => \Intervention\Application\Contract\Event\Publication\InterventionPublishedEvent::class,
    'Intervention\Domain\Event\Recurrence\InterventionRecurrenceCreatedEvent' => \Intervention\Application\Contract\Event\Recurrence\InterventionRecurrenceCreatedEvent::class,
    'Intervention\Domain\Event\Recurrence\InterventionRecurrenceDeletedEvent' => \Intervention\Application\Contract\Event\Recurrence\InterventionRecurrenceDeletedEvent::class,
    'Intervention\Domain\Event\Recurrence\InterventionRecurrenceMaterializedEvent' => \Intervention\Application\Contract\Event\Recurrence\InterventionRecurrenceMaterializedEvent::class,
    'Intervention\Domain\Event\Recurrence\InterventionRecurrenceUpdatedEvent' => \Intervention\Application\Contract\Event\Recurrence\InterventionRecurrenceUpdatedEvent::class,
    'Intervention\Domain\Event\Workflow\InterventionStatusTransitionedEvent' => \Intervention\Application\Contract\Event\Workflow\InterventionStatusTransitionedEvent::class,
    'Intervention\Domain\Event\Workflow\InterventionWorkflowNotificationRequestedEvent' => \Intervention\Application\Contract\Event\Workflow\InterventionWorkflowNotificationRequestedEvent::class,
  ];
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the technical copier without exposing legacy Domain types across module boundaries.
   *
   * @access public
   *
   * @param IntegrationEventSnapshotPort $snapshots copies persisted state without rerunning constructors
   *
   * @return void
   */
  public function __construct(private IntegrationEventSnapshotPort $snapshots)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method convert
   *
   * Preserves all snapshot properties, including the original occurrence time and event identity.
   *
   * @access public
   *
   * @param object $event an already deserialized legacy or current event
   *
   * @return ?object the public event, or null when this owner does not recognize the legacy class
   */
  public function convert(object $event): ?object
  {
    $contract = self::CONTRACTS[$event::class] ?? null;

    return null === $contract ? null : $this->snapshots->copy($event, $contract);
  }
  // #endregion
}
