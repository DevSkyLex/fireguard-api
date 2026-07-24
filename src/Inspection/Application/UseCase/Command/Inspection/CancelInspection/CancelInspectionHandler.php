<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CancelInspection;

use Inspection\Application\Port\Outbound\InspectionRepositoryPort;
use Inspection\Domain\Event\Inspection\InspectionCancelledEvent;
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\Exception\InvalidValueException;

final readonly class CancelInspectionHandler implements CommandHandler
{
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  public function __invoke(CancelInspectionCommand $command): CancelInspectionResult
  {
    try {
      $inspectionId = InspectionId::fromString($command->inspectionId);
      $organizationId = InspectionOrganizationId::fromString($command->organizationId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

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
}
