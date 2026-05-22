<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityDescendants;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;

final readonly class GetFacilityDescendantsHandler implements QueryHandler
{
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
  ) {
  }

  public function __invoke(GetFacilityDescendantsQuery $query): GetFacilityDescendantsResult
  {
    try {
      $organizationId = FacilityOrganizationId::fromString($query->organizationId);
      $facilityId = FacilityId::fromString($query->facilityId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($query->facilityId);
    }

    $descendants = $this->facilityRepository->findDescendants(
      organizationId: $organizationId,
      facilityId: $facilityId,
      includeArchived: $query->includeArchived,
      search: $query->search,
      sorting: $query->sorting,
    );

    return new GetFacilityDescendantsResult(array_map(
      static fn ($facility): GetFacilityResult => new GetFacilityResult(
        facilityId: (string) $facility->id(),
        organizationId: (string) $facility->organizationId(),
        parentFacilityId: $facility->parentFacilityId()?->__toString(),
        type: $facility->type()->value,
        name: (string) $facility->name(),
        code: $facility->code(),
        status: $facility->status()->value,
        address: $facility->address(),
        metadata: $facility->metadata(),
        createdAt: $facility->createdAt(),
        updatedAt: $facility->updatedAt(),
      ),
      $descendants,
    ));
  }
}
