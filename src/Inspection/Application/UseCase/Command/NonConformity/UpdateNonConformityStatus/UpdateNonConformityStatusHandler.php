<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\NonConformity\UpdateNonConformityStatus;

use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Domain\Event\NonConformity\NonConformityStatusChangedEvent;
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityNotFoundException};
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId, NonConformityId, NonConformityStatus};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

/**
 * UseCase UpdateNonConformityStatusHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateNonConformityStatusHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private NonConformityRepositoryPort $nonConformityRepository,
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
  public function __invoke(UpdateNonConformityStatusCommand $command): UpdateNonConformityStatusResult
  {
    try {
      $organizationId = InspectionOrganizationId::fromString($command->organizationId);
      $inspectionId = InspectionId::fromString($command->inspectionId);
      $nonConformityId = NonConformityId::fromString($command->nonConformityId);
      $status = NonConformityStatus::from($command->status);
    } catch (InvalidValueException|ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    // Verify inspection exists and belongs to the organization
    $inspection = $this->inspectionRepository->findById($inspectionId);

    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($command->inspectionId);
    }

    $nonConformity = $this->nonConformityRepository->findById($nonConformityId);

    if (null === $nonConformity || (string) $nonConformity->inspectionId() !== $command->inspectionId) {
      throw NonConformityNotFoundException::withId($command->nonConformityId);
    }

    $previousStatus = $nonConformity->status()->value;

    $nonConformity->updateStatus($status);

    $this->nonConformityRepository->save($nonConformity);

    // A same-status update is a no-op, not a transition: skip the audit event.
    if ($previousStatus !== $nonConformity->status()->value) {
      $this->eventDispatcher->dispatch(new NonConformityStatusChangedEvent(
        organizationId: $command->organizationId,
        inspectionId: $command->inspectionId,
        nonConformityId: $command->nonConformityId,
        previousStatus: $previousStatus,
        status: $nonConformity->status()->value,
      ));
    }

    return new UpdateNonConformityStatusResult(
      nonConformityId: (string) $nonConformity->id(),
      inspectionId: (string) $nonConformity->inspectionId(),
      description: $nonConformity->description(),
      severity: $nonConformity->severity()->value,
      status: $nonConformity->status()->value,
      dueAt: $nonConformity->dueAt()?->format('c'),
      resolvedAt: $nonConformity->resolvedAt()?->format('c'),
      notes: $nonConformity->notes(),
      createdAt: $nonConformity->createdAt(),
      updatedAt: $nonConformity->updatedAt(),
    );
  }
  // #endregion
}
