<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityChildren;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;

final readonly class GetFacilityChildrenHandler implements QueryHandler
{
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
  ) {
  }

  public function __invoke(GetFacilityChildrenQuery $query): GetFacilityChildrenResult
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

    $children = $this->facilityRepository->findChildren(
      organizationId: $organizationId,
      facilityId: $facilityId,
      includeArchived: $query->includeArchived,
      search: $query->search,
      sorting: $query->sorting,
    );

    return new GetFacilityChildrenResult(array_map(
      static fn ($child): GetFacilityResult => new GetFacilityResult(
        facilityId: (string) $child->id(),
        organizationId: (string) $child->organizationId(),
        parentFacilityId: $child->parentFacilityId()?->__toString(),
        type: $child->type()->value,
        name: (string) $child->name(),
        code: $child->code(),
        status: $child->status()->value,
        address: $child->address(),
        metadata: $child->metadata(),
        createdAt: $child->createdAt(),
        updatedAt: $child->updatedAt(),
      ),
      $children,
    ));
  }
}
