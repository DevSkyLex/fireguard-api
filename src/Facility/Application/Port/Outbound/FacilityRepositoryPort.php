<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

/**
 * Port FacilityRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a facility aggregate.
   *
   * @since 1.0.0
   *
   * @param Facility $facility the facility aggregate
   */
  public function save(Facility $facility): void;

  /**
   * Method findById.
   *
   * Finds a facility by identifier.
   *
   * @since 1.0.0
   *
   * @param FacilityId $id the facility identifier
   *
   * @return ?Facility the facility aggregate when found
   */
  public function findById(FacilityId $id): ?Facility;

  /**
   * Method findPublishedById.
   *
   * Finds a PUBLISHED facility by identifier. Draft intervention
   * scratchpads are invisible to this lookup: the lifecycle commands
   * (archive, restore, move) must not act on — nor audit — records
   * that only exist inside an unpublished intervention.
   *
   * @since 1.0.0
   *
   * @param FacilityId $id the facility identifier
   *
   * @return ?Facility the published facility aggregate when found
   */
  public function findPublishedById(FacilityId $id): ?Facility;

  /**
   * Method findChildren.
   *
   * Lists direct children for a facility.
   *
   * @return list<Facility>
   */
  public function findChildren(
    FacilityOrganizationId $organizationId,
    FacilityId $facilityId,
    bool $includeArchived = false,
    ?string $search = null,
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
  ): array;

  /**
   * Counts direct children for a facility.
   */
  public function countChildren(
    FacilityOrganizationId $organizationId,
    FacilityId $facilityId,
    bool $includeArchived = false,
    ?string $search = null,
  ): int;

  /**
   * Counts direct children grouped by parent facility identifier.
   *
   * @param list<FacilityId> $parentIds
   *
   * @return array<string, int>
   */
  public function countChildrenByParentIds(
    FacilityOrganizationId $organizationId,
    array $parentIds,
    bool $includeArchived = false,
  ): array;

  /**
   * Method findDescendants.
   *
   * Lists all descendants for a facility.
   *
   * @return list<Facility>
   */
  public function findDescendants(
    FacilityOrganizationId $organizationId,
    FacilityId $facilityId,
    bool $includeArchived = false,
    ?string $search = null,
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
  ): array;

  /**
   * Method hasActiveDescendants.
   *
   * Reports whether the facility has at least one active (non-archived,
   * published) descendant anywhere in its sub-tree, without loading the tree.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param FacilityId $facilityId the root facility identifier
   *
   * @return bool whether an active descendant exists
   */
  public function hasActiveDescendants(FacilityOrganizationId $organizationId, FacilityId $facilityId): bool;

  /**
   * Method depthOf.
   *
   * Reports the facility's depth in its hierarchy, walking upward through
   * PUBLISHED ancestors only. A root facility (no parent) sits at depth 1.
   *
   * @since 1.0.0
   *
   * @param FacilityId $facilityId the facility identifier
   *
   * @return int the facility depth, root = 1
   */
  public function depthOf(FacilityId $facilityId): int;

  /**
   * Method subtreeHeight.
   *
   * Reports the height of the facility's sub-tree, walking downward through
   * PUBLISHED descendants only. A facility with no descendants has height 0;
   * one with only direct children has height 1, and so on.
   *
   * @since 1.0.0
   *
   * @param FacilityId $facilityId the sub-tree root facility identifier
   *
   * @return int the sub-tree height, leaf = 0
   */
  public function subtreeHeight(FacilityId $facilityId): int;

  /**
   * Method countByOrganizationId.
   *
   * Counts facilities for an organization with optional filters.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param bool $includeArchived whether archived facilities are included by default when no explicit status filter is provided
   * @param ?string $type optional type filter
   * @param ?string $status optional status filter
   * @param ?string $parentFacilityId optional parent facility filter
   * @param ?string $code optional exact code filter
   * @param ?string $search optional text search applied before counting
   * @param bool $rootsOnly whether only facilities without parent are counted
   * @param ?bool $hasCoordinates when true, count only facilities with both latitude and longitude set; when false, count only facilities missing coordinates; null applies no coordinate filtering
   *
   * @return int the facilities count
   */
  public function countByOrganizationId(
    FacilityOrganizationId $organizationId,
    bool $includeArchived = false,
    ?string $type = null,
    ?string $status = null,
    ?string $parentFacilityId = null,
    ?string $code = null,
    ?string $search = null,
    bool $rootsOnly = false,
    ?bool $hasCoordinates = null,
  ): int;

  /**
   * Method countActiveByOrganizationId.
   *
   * Counts active (non-archived) facilities belonging to an organization.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   *
   * @return int the active facility count
   */
  public function countActiveByOrganizationId(FacilityOrganizationId $organizationId): int;

  /**
   * Counts dashboard overview metrics for facilities in one query.
   *
   * @return array{total: int, active: int}
   */
  public function countOverviewByOrganizationId(
    FacilityOrganizationId $organizationId,
    ?string $type = null,
  ): array;

  /**
   * Counts facilities grouped by type for an organization.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param bool $includeArchived whether archived facilities are included
   *
   * @return array<string, int> map of type => count
   */
  public function countByTypeForOrganizationId(
    FacilityOrganizationId $organizationId,
    bool $includeArchived = false,
  ): array;

  /**
   * Counts facilities grouped by creation day for an organization.
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countByCreatedDayForOrganizationId(
    FacilityOrganizationId $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    ?string $timeZone = null,
    ?string $type = null,
  ): array;

  /**
   * Method getFacilityNamesByIds.
   *
   * Resolves facility display names for a bounded set of identifiers,
   * scoped to the organization.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param list<string> $facilityIds the facility identifiers to resolve
   *
   * @return array<string, string> map of facilityId => name
   */
  public function getFacilityNamesByIds(FacilityOrganizationId $organizationId, array $facilityIds): array;

  /**
   * Method getFacilityCodesByIds.
   *
   * Resolves facility `code` values for a bounded set of identifiers, scoped
   * to the organization — backs the CSV export's `parentCode` column, which
   * mirrors {@see self::getFacilityNamesByIds()} but for the field
   * {@see \Import\Application\Service\FacilityRowFactory} reads back on
   * import. A facility with no code is simply absent from the returned map.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param list<string> $facilityIds the facility identifiers to resolve
   *
   * @return array<string, string> map of facilityId => code
   */
  public function getFacilityCodesByIds(FacilityOrganizationId $organizationId, array $facilityIds): array;

  /**
   * Method findByOrganizationId.
   *
   * Lists facilities for an organization with optional filters.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param bool $includeArchived whether archived facilities are included by default when no explicit status filter is provided
   * @param ?string $type optional type filter
   * @param ?string $status optional status filter
   * @param ?string $parentFacilityId optional parent facility filter
   * @param ?string $code optional exact code filter
   * @param ?string $search optional text search applied before pagination
   * @param Sorting $sorting requested sorting applied before pagination
   * @param int $limit maximum number of results
   * @param int $offset result offset
   * @param bool $rootsOnly whether only facilities without parent are listed
   * @param ?bool $hasCoordinates when true, list only facilities with both latitude and longitude set; when false, list only facilities missing coordinates; null applies no coordinate filtering
   *
   * @return list<Facility> the facilities collection
   */
  public function findByOrganizationId(
    FacilityOrganizationId $organizationId,
    bool $includeArchived = false,
    ?string $type = null,
    ?string $status = null,
    ?string $parentFacilityId = null,
    ?string $code = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
    bool $rootsOnly = false,
    ?bool $hasCoordinates = null,
  ): array;

  /**
   * Method findAncestors.
   *
   * Resolves the ancestor breadcrumb for a facility by walking its parent
   * chain upward over PUBLISHED records, ordered root first (direct parent
   * last), excluding the facility itself. Empty for a root facility.
   *
   * @since 1.0.0
   *
   * @param string $facilityId the facility identifier whose ancestors are resolved
   *
   * @return list<array{id: string, name: string, type: string}> the ancestor breadcrumb, root first
   */
  public function findAncestors(string $facilityId): array;

  /**
   * Method findZonesForPlanAttachment.
   *
   * Lists every published facility, self-or-descendant of `$rootFacilityId`,
   * whose `planGeometry` is bound to `$attachmentId` — a single recursive
   * CTE joined with a JSONB equality filter, so the overlay read never
   * hydrates the whole subtree just to discard most of it.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param FacilityId $rootFacilityId the facility the overlay was requested for (included)
   * @param string $attachmentId the floor plan attachment identifier
   *
   * @return list<array{facilityId: string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>}> the matching zones
   */
  public function findZonesForPlanAttachment(
    FacilityOrganizationId $organizationId,
    FacilityId $rootFacilityId,
    string $attachmentId,
  ): array;

  /**
   * Method findBuildingFloors.
   *
   * Lists the direct children of a building that are floors — published,
   * ordered by their stacking level, then creation, then identifier — each
   * carrying its own plan geometry (if any) and the attachment identity of
   * its primary floor plan (if any). Raw rows only: no contour cascade, no
   * leaf filtering — the 3D building view use case applies those rules.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param FacilityId $buildingId the building facility identifier
   *
   * @return list<array{
   *   facilityId: string,
   *   name: string,
   *   status: string,
   *   levelIndex: ?int,
   *   planGeometry: ?array{attachmentId: string, points: list<array{0: float, 1: float}>},
   *   primaryPlanAttachmentId: ?string,
   *   primaryPlanImageWidth: ?int,
   *   primaryPlanImageHeight: ?int,
   * }> the building's floors, in render order
   */
  public function findBuildingFloors(
    FacilityOrganizationId $organizationId,
    FacilityId $buildingId,
  ): array;

  /**
   * Method findRoomsForFloors.
   *
   * For each given (floor, its primary plan attachment) pair, lists the
   * strict descendants of that floor which are zones or areas bound to that
   * same plan attachment — a single recursive CTE seeded from all the
   * bindings at once, never one query per floor. Raw rows only: leaf
   * filtering by `parentFacilityId` is the use case's job.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param list<array{floorId: string, attachmentId: string}> $floorPlanBindings the floors and their primary plan attachment identifiers
   *
   * @return list<array{
   *   floorId: string,
   *   facilityId: string,
   *   parentFacilityId: ?string,
   *   name: string,
   *   type: string,
   *   status: string,
   *   points: list<array{0: float, 1: float}>,
   * }> the matching rooms, unordered
   */
  public function findRoomsForFloors(
    FacilityOrganizationId $organizationId,
    array $floorPlanBindings,
  ): array;

  // #endregion
}
