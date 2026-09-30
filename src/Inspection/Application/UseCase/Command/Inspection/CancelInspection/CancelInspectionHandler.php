<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CancelInspection;

use Inspection\Application\Port\Outbound\InspectionRepositoryPort;
use Inspection\Domain\Event\Inspection\InspectionCancelledEvent;
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Class CancelInspectionHandler
 *
 * Cancels a published inspection and dispatches its resulting domain events.
 *
 * @category Handler
 */
final readonly class CancelInspectionHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Provides the inspection lock and repository used to serialize and persist cancellation.
   *
   * @access public
   *
   * @param InspectionRepositoryPort $inspectionRepository loads and saves inspection aggregates
   * @param EventDispatcherPort $eventDispatcher dispatches events released by the aggregate
   *
   * @return void
   */
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  // #endregion
  // #region Methods
  /**
   * Method __invoke.
   *
   * Cancels an inspection after confirming it belongs to the requested organization.
   *
   * @access public
   *
   * @param CancelInspectionCommand $command the organization and inspection identifiers
   *
   * @return CancelInspectionResult the cancelled inspection result
   *
   * @throws InspectionNotFoundException when the inspection is absent or outside the organization
   */
  public function __invoke(CancelInspectionCommand $command): CancelInspectionResult
  {
    $inspectionId = InspectionId::fromString($command->inspectionId);
    $organizationId = InspectionOrganizationId::fromString($command->organizationId);

    $inspection = $this->inspectionRepository->findPublishedById($inspectionId);

    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($command->inspectionId);
    }

    // Logical cancellation: a draft or submitted inspection transitions to
    // `cancelled` (the aggregate rejects closed/already-cancelled) instead of a
    // physical delete, so the row and its non-conformities are preserved.
    $previousStatus = $inspection->status()->value;
    $inspection->cancel();
    $this->inspectionRepository->save($inspection);

    // Emitted after the durable save so a failed persistence leaves no ledger row.
    $this->eventDispatcher->dispatch(new InspectionCancelledEvent(
      organizationId: (string) $inspection->organizationId(),
      inspectionId: (string) $inspection->id(),
      equipmentId: (string) $inspection->equipmentId(),
      previousStatus: $previousStatus,
    ));

    return new CancelInspectionResult(
      inspectionId: $command->inspectionId,
      organizationId: $command->organizationId,
    );
  }
  // #endregion
}
