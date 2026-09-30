<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\GetNonConformity;

use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityNotFoundException};
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId, NonConformityId};
use Shared\Application\Message\QueryHandler;

/**
 * Class GetNonConformityHandler
 *
 * Loads one non-conformity after confirming its inspection belongs to the requested organization.
 *
 * @category Handler
 */
final readonly class GetNonConformityHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Provides repositories for validating the inspection and loading its non-conformity.
   *
   * @access public
   *
   * @param InspectionRepositoryPort $inspectionRepository reads the inspection aggregate
   * @param NonConformityRepositoryPort $nonConformityRepository reads the non-conformity aggregate
   *
   * @return void
   */
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private NonConformityRepositoryPort $nonConformityRepository,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * Returns non-conformity details only when both the inspection and item match the request.
   *
   * @access public
   *
   * @param GetNonConformityQuery $query identifies the organization, inspection and item
   *
   * @return GetNonConformityResult requested non-conformity details
   *
   * @throws InspectionNotFoundException when the inspection is missing or outside the organization
   * @throws NonConformityNotFoundException when the item is missing or belongs to another inspection
   */
  public function __invoke(GetNonConformityQuery $query): GetNonConformityResult
  {
    $organizationId = InspectionOrganizationId::fromString($query->organizationId);
    $inspectionId = InspectionId::fromString($query->inspectionId);
    $nonConformityId = NonConformityId::fromString($query->nonConformityId);

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
  // #endregion
}
