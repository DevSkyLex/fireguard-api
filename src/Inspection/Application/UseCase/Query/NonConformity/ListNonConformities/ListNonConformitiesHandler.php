<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\ListNonConformities;

use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId, NonConformityInspectionId, NonConformitySeverity, NonConformityStatus};
use InvalidArgumentException;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

/**
 * UseCase ListNonConformitiesHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListNonConformitiesHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private NonConformityRepositoryPort $nonConformityRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(ListNonConformitiesQuery $query): ListNonConformitiesResult
  {
    try {
      $organizationId = InspectionOrganizationId::fromString($query->organizationId);
      $inspectionId = InspectionId::fromString($query->inspectionId);
      $severity = null !== $query->severity ? NonConformitySeverity::from($query->severity)->value : null;
      $status = null !== $query->status ? NonConformityStatus::from($query->status)->value : null;
    } catch (InvalidValueException|ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $inspection = $this->inspectionRepository->findById($inspectionId);

    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($query->inspectionId);
    }

    $nonConformities = $this->nonConformityRepository->findByInspectionId(
      NonConformityInspectionId::fromString($query->inspectionId),
      $severity,
      $status,
    );

    $results = [];

    foreach ($nonConformities as $nonConformity) {
      $results[] = new NonConformityResult(
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

    return new ListNonConformitiesResult($results);
  }
  // #endregion
}
