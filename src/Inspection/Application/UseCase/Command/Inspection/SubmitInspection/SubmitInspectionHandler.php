<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\SubmitInspection;

use Inspection\Application\Port\Outbound\InspectionRepositoryPort;
use Inspection\Domain\Event\Inspection\InspectionSubmittedEvent;
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase SubmitInspectionHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SubmitInspectionHandler implements CommandHandler
{
  // #region Constructor
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
   * @since 1.0.0
   */
  public function __invoke(SubmitInspectionCommand $command): SubmitInspectionResult
  {
    $inspectionId = InspectionId::fromString($command->inspectionId);
    $organizationId = InspectionOrganizationId::fromString($command->organizationId);

    $inspection = $this->inspectionRepository->findPublishedById($inspectionId);

    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($command->inspectionId);
    }

    $inspection->submit();

    $this->inspectionRepository->save($inspection);

    $this->eventDispatcher->dispatch(new InspectionSubmittedEvent(
      organizationId: (string) $inspection->organizationId(),
      inspectionId: (string) $inspection->id(),
      equipmentId: (string) $inspection->equipmentId(),
      result: $inspection->result()->value,
    ));

    return new SubmitInspectionResult(
      inspectionId: (string) $inspection->id(),
      status: $inspection->status()->value,
      updatedAt: $inspection->updatedAt(),
    );
  }
  // #endregion
}
