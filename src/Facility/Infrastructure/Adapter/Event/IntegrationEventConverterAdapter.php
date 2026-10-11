<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Event;

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
    'Facility\Domain\Event\Export\FacilitiesExportedEvent' => \Facility\Application\Contract\Event\Export\FacilitiesExportedEvent::class,
    'Facility\Domain\Event\Facility\FacilityArchivedEvent' => \Facility\Application\Contract\Event\Facility\FacilityArchivedEvent::class,
    'Facility\Domain\Event\Facility\FacilityCreatedEvent' => \Facility\Application\Contract\Event\Facility\FacilityCreatedEvent::class,
    'Facility\Domain\Event\Facility\FacilityMovedEvent' => \Facility\Application\Contract\Event\Facility\FacilityMovedEvent::class,
    'Facility\Domain\Event\Facility\FacilityRestoredEvent' => \Facility\Application\Contract\Event\Facility\FacilityRestoredEvent::class,
    'Facility\Domain\Event\Facility\FacilitySubtreeDuplicatedEvent' => \Facility\Application\Contract\Event\Facility\FacilitySubtreeDuplicatedEvent::class,
    'Facility\Domain\Event\Facility\FacilityUpdatedEvent' => \Facility\Application\Contract\Event\Facility\FacilityUpdatedEvent::class,
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
