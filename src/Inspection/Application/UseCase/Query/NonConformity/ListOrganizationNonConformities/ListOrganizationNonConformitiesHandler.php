<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\ListOrganizationNonConformities;

use Inspection\Application\Port\Outbound\{EquipmentNamingPort, InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Domain\ValueObject\{InspectionOrganizationId, NonConformitySeverity, NonConformityStatus};
use InvalidArgumentException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

use function array_keys;

/**
 * UseCase ListOrganizationNonConformitiesHandler.
 *
 * Lists an organization's non-conformities across every inspection, newest
 * first by default. Unlike the per-inspection listing, this handler never
 * checks a single inspection's existence — organization scoping is enforced
 * entirely by the repository's join, matching how
 * {@see \Inspection\Application\UseCase\Query\Inspection\ListInspections\ListInspectionsHandler}
 * lists across an organization.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationNonConformitiesHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private NonConformityRepositoryPort $nonConformityRepository,
    private InspectionRepositoryPort $inspectionRepository,
    private EquipmentNamingPort $equipmentNaming,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @return PaginatedResult<OrganizationNonConformityResult>
   */
  public function __invoke(ListOrganizationNonConformitiesQuery $query): PaginatedResult
  {
    try {
      $organizationId = InspectionOrganizationId::fromString($query->organizationId);
      $severity = null !== $query->severity ? NonConformitySeverity::from($query->severity)->value : null;
      $status = null !== $query->status ? NonConformityStatus::from($query->status)->value : null;
    } catch (InvalidValueException|ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $nonConformities = $this->nonConformityRepository->findByOrganizationId(
      $organizationId,
      $severity,
      $status,
      $query->search,
      $query->sorting,
      $query->pagination->limit,
      $query->pagination->offset,
    );

    $total = $this->nonConformityRepository->countByOrganizationId(
      $organizationId,
      $severity,
      $status,
      $query->search,
    );

    // Resolved once for the whole page: the aggregate only knows its
    // inspection, and naming the equipment it was performed on is a
    // two-hop lookup (non-conformity -> inspection -> equipment) that must
    // not become an N+1 across a page of rows.
    $inspectionIds = [];
    foreach ($nonConformities as $nonConformity) {
      $inspectionIds[(string) $nonConformity->inspectionId()] = true;
    }

    $equipmentIdsByInspectionId = $this->inspectionRepository->findEquipmentIdsByIds(array_keys($inspectionIds));

    $equipmentIds = [];
    foreach ($equipmentIdsByInspectionId as $equipmentId) {
      $equipmentIds[$equipmentId] = true;
    }

    $serialNumbers = $this->equipmentNaming->findSerialNumbersByIds(array_keys($equipmentIds));

    $results = [];
    foreach ($nonConformities as $nonConformity) {
      $inspectionId = (string) $nonConformity->inspectionId();
      $equipmentId = $equipmentIdsByInspectionId[$inspectionId] ?? null;

      $results[] = new OrganizationNonConformityResult(
        nonConformityId: (string) $nonConformity->id(),
        inspectionId: $inspectionId,
        description: $nonConformity->description(),
        severity: $nonConformity->severity()->value,
        status: $nonConformity->status()->value,
        dueAt: $nonConformity->dueAt()?->format('c'),
        resolvedAt: $nonConformity->resolvedAt()?->format('c'),
        notes: $nonConformity->notes(),
        createdAt: $nonConformity->createdAt(),
        updatedAt: $nonConformity->updatedAt(),
        equipmentId: $equipmentId,
        equipmentSerialNumber: null !== $equipmentId ? ($serialNumbers[$equipmentId] ?? null) : null,
      );
    }

    return new PaginatedResult(
      items: $results,
      total: $total,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
    );
  }
  // #endregion
}
