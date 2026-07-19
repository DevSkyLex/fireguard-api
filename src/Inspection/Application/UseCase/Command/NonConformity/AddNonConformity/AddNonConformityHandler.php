<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\NonConformity\AddNonConformity;

use DateTimeImmutable;
use Exception;
use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Domain\Event\NonConformity\NonConformityRecordedEvent;
use Inspection\Domain\Exception\{InspectionAlreadyClosedException, InspectionNotFoundException};
use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{
  InspectionId,
  InspectionOrganizationId,
  NonConformityId,
  NonConformityInspectionId,
  NonConformitySeverity
};
use InvalidArgumentException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

/**
 * UseCase AddNonConformityHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddNonConformityHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private NonConformityRepositoryPort $nonConformityRepository,
    private UuidFactory $uuidFactory,
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
  public function __invoke(AddNonConformityCommand $command): AddNonConformityResult
  {
    try {
      $organizationId = InspectionOrganizationId::fromString($command->organizationId);
      $inspectionId = InspectionId::fromString($command->inspectionId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $inspection = $this->inspectionRepository->findById($inspectionId);

    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($command->inspectionId);
    }

    if ($inspection->status()->isClosed()) {
      throw InspectionAlreadyClosedException::withId($command->inspectionId);
    }

    try {
      $severity = NonConformitySeverity::from($command->severity);
      $dueAt = null !== $command->dueAt ? new DateTimeImmutable($command->dueAt) : null;

      /** @var NonConformityId $nonConformityId */
      $nonConformityId = $this->uuidFactory->create(NonConformityId::class);

      $nonConformity = NonConformity::create(
        id: $nonConformityId,
        inspectionId: NonConformityInspectionId::fromString($command->inspectionId),
        description: $command->description,
        severity: $severity,
        dueAt: $dueAt,
        notes: $command->notes,
      );
    } catch (InvalidValueException|ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    } catch (Exception $exception) {
      if (!$exception instanceof InvalidArgumentException) {
        throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
      }

      throw $exception;
    }

    $this->nonConformityRepository->save($nonConformity);

    $this->eventDispatcher->dispatch(new NonConformityRecordedEvent(
      organizationId: $command->organizationId,
      inspectionId: $command->inspectionId,
      nonConformityId: (string) $nonConformity->id(),
      severity: $nonConformity->severity()->value,
    ));

    return new AddNonConformityResult(
      nonConformityId: (string) $nonConformity->id(),
      inspectionId: (string) $nonConformity->inspectionId(),
      description: $nonConformity->description(),
      severity: $nonConformity->severity()->value,
      status: $nonConformity->status()->value,
      dueAt: $nonConformity->dueAt()?->format('c'),
      notes: $nonConformity->notes(),
      createdAt: $nonConformity->createdAt(),
      updatedAt: $nonConformity->updatedAt(),
    );
  }
  // #endregion
}
