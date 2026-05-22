<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CancelInspection;

use Inspection\Application\Port\Outbound\InspectionRepositoryPort;
use Inspection\Domain\Exception\{InspectionAlreadyClosedException, InspectionAlreadySubmittedException, InspectionNotFoundException};
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;

final readonly class CancelInspectionHandler implements CommandHandler
{
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
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

    $inspection = $this->inspectionRepository->findById($inspectionId);

    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($command->inspectionId);
    }

    if ($inspection->status()->isClosed()) {
      throw InspectionAlreadyClosedException::withId($command->inspectionId);
    }

    if (!$inspection->status()->isDraft()) {
      throw InspectionAlreadySubmittedException::withId($command->inspectionId);
    }

    $this->inspectionRepository->remove($inspection);

    return new CancelInspectionResult(
      inspectionId: $command->inspectionId,
      organizationId: $command->organizationId,
    );
  }
}
