<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Event;

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
    'Inspection\Domain\Event\Export\InspectionReportExportedEvent' => \Inspection\Application\Contract\Event\Export\InspectionReportExportedEvent::class,
    'Inspection\Domain\Event\Export\InspectionsExportedEvent' => \Inspection\Application\Contract\Event\Export\InspectionsExportedEvent::class,
    'Inspection\Domain\Event\Export\NonConformitiesExportedEvent' => \Inspection\Application\Contract\Event\Export\NonConformitiesExportedEvent::class,
    'Inspection\Domain\Event\Export\NonConformitiesReportExportedEvent' => \Inspection\Application\Contract\Event\Export\NonConformitiesReportExportedEvent::class,
    'Inspection\Domain\Event\Inspection\InspectionCancelledEvent' => \Inspection\Application\Contract\Event\Inspection\InspectionCancelledEvent::class,
    'Inspection\Domain\Event\Inspection\InspectionClosedEvent' => \Inspection\Application\Contract\Event\Inspection\InspectionClosedEvent::class,
    'Inspection\Domain\Event\Inspection\InspectionSubmittedEvent' => \Inspection\Application\Contract\Event\Inspection\InspectionSubmittedEvent::class,
    'Inspection\Domain\Event\NonConformity\NonConformityRecordedEvent' => \Inspection\Application\Contract\Event\NonConformity\NonConformityRecordedEvent::class,
    'Inspection\Domain\Event\NonConformity\NonConformityStatusChangedEvent' => \Inspection\Application\Contract\Event\NonConformity\NonConformityStatusChangedEvent::class,
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
