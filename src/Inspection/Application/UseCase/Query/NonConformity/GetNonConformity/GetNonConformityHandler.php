<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\GetNonConformity;

use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityNotFoundException};
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId, NonConformityId};
use InvalidArgumentException;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

final readonly class GetNonConformityHandler implements QueryHandler
{
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private NonConformityRepositoryPort $nonConformityRepository,
  ) {
  }

  public function __invoke(GetNonConformityQuery $query): GetNonConformityResult
  {
    try {
      $organizationId = InspectionOrganizationId::fromString($query->organizationId);
      $inspectionId = InspectionId::fromString($query->inspectionId);
      $nonConformityId = NonConformityId::fromString($query->nonConformityId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $inspection = $this->inspectionRepository->findById($inspectionId);

    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($query->inspectionId);
    }

    $nonConformity = $this->nonConformityRepository->findById($nonConformityId);

    if (null === $nonConformity || (string) $nonConformity->inspectionId() !== $query->inspectionId) {
      throw NonConformityNotFoundException::withId($query->nonConformityId);
    }

    return new GetNonConformityResult(
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
}
