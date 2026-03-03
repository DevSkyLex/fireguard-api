<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CloseInspection;

use Inspection\Application\Port\Outbound\InspectionRepositoryPort;
use Inspection\Domain\Exception\{InspectionNotFoundException};
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;

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

    $inspection->close();

    $this->inspectionRepository->save($inspection);

    return new CloseInspectionResult(
      inspectionId: (string) $inspection->id(),
      status: $inspection->status()->value,
      updatedAt: $inspection->updatedAt(),
    );
  }
  // #endregion
}
