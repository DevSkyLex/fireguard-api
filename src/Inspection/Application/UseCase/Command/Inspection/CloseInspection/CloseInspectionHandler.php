<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CloseInspection;

use Inspection\Application\Port\Outbound\{InspectionMaintenanceSynchronizerPort, InspectionRepositoryPort};
use Inspection\Domain\Event\Inspection\InspectionClosedEvent;
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId};
use Psr\Log\LoggerInterface;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Throwable;

/**
 * UseCase CloseInspectionHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CloseInspectionHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private EventDispatcherPort $eventDispatcher,
    private InspectionMaintenanceSynchronizerPort $maintenanceSynchronizer,
    private LoggerInterface $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(CloseInspectionCommand $command): CloseInspectionResult
  {
    $inspectionId = InspectionId::fromString($command->inspectionId);
    $organizationId = InspectionOrganizationId::fromString($command->organizationId);

    $inspection = $this->inspectionRepository->findPublishedById($inspectionId);

    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($command->inspectionId);
    }

    $inspection->close();

    $this->inspectionRepository->save($inspection);

    $this->eventDispatcher->dispatch(new InspectionClosedEvent(
      organizationId: (string) $inspection->organizationId(),
      inspectionId: (string) $inspection->id(),
      equipmentId: (string) $inspection->equipmentId(),
      result: $inspection->result()->value,
    ));

    // Best-effort, post-commit: the maintenance schedule recompute must
    // never turn a successful inspection closure into a failure.
    try {
      $this->maintenanceSynchronizer->onInspectionClosed(
        (string) $inspection->organizationId(),
        (string) $inspection->equipmentId(),
        $inspection->updatedAt(),
      );
    } catch (Throwable $exception) {
      $this->logger->error('Failed to synchronize the maintenance schedule after inspection closure.', [
        'inspectionId' => (string) $inspection->id(),
        'equipmentId' => (string) $inspection->equipmentId(),
        'error' => $exception->getMessage(),
      ]);
    }

    return new CloseInspectionResult(
      inspectionId: (string) $inspection->id(),
      status: $inspection->status()->value,
      updatedAt: $inspection->updatedAt(),
    );
  }
  // #endregion
}
