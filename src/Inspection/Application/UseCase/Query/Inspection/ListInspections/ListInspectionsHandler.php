<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ListInspections;

use DateTimeImmutable;
use Exception;
use Inspection\Application\Contract\Inspection\{InspectionExecutionCriteria, InspectionInspectorCriteria, InspectionListCriteria, InspectionSubjectCriteria};
use Inspection\Application\Port\Outbound\{EquipmentNamingPort, FacilityNamingPort};
use Inspection\Application\Port\Outbound\Internal\{ChecklistRepositoryPort, InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{
  InspectionChecklistId,
  InspectionEquipmentId,
  InspectionFacilityId,
  InspectionOrganizationId,
  InspectionResult,
  InspectionStatus
};
use LogicException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;
use Throwable;
use User\Application\Contract\User\UserView;
use User\Application\Port\Inbound\UserDirectoryPort;
use ValueError;

use function array_keys;

/**
 * Class ListInspectionsHandler
 *
 * Lists inspections with their related non-conformity counts and display names.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInspectionsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Combines inspection and finding data with equipment, facility, and checklist display names.
   *
   * @access public
   *
   * @param InspectionRepositoryPort $inspectionRepository loads organization inspections
   * @param NonConformityRepositoryPort $nonConformityRepository counts related non-conformities
   * @param EquipmentNamingPort $equipmentNaming resolves equipment display names
   * @param FacilityNamingPort $facilityNaming resolves facility display names
   * @param ChecklistRepositoryPort $checklistRepository loads checklist names
   *
   * @return void
   */
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private NonConformityRepositoryPort $nonConformityRepository,
    private EquipmentNamingPort $equipmentNaming,
    private FacilityNamingPort $facilityNaming,
    private ChecklistRepositoryPort $checklistRepository,
    private ?\Equipment\Application\Port\Inbound\EquipmentParkScopePort $parkScopes = null,
    private ?UserDirectoryPort $users = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @return PaginatedResult<GetInspectionResult>
   */
  public function __invoke(ListInspectionsQuery $query): PaginatedResult
  {
    $organizationId = $this->organizationId($query->organizationId);
    $criteria = $this->criteria($query);
    $inspections = $this->inspectionRepository->findByOrganizationId(
      organizationId: $organizationId,
      criteria: $criteria,
      sorting: $query->sorting,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
    );
    $total = $this->inspectionRepository->countByOrganizationId($organizationId, $criteria);

    return new PaginatedResult(
      items: $this->mapResults($inspections),
      total: $total,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
    );
  }

  /**
   * Method organizationId
   *
   * Normalizes the owning organization before the remaining collection filters.
   *
   * @access private
   *
   * @param string $id the requested organization identifier
   *
   * @return InspectionOrganizationId the validated organization identifier
   */
  private function organizationId(string $id): InspectionOrganizationId
  {
    try {
      return InspectionOrganizationId::fromString($id);
    } catch (Exception $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }
  }

  /**
   * Method criteria
   *
   * Parses filters in their existing order before resolving the optional parc scope.
   *
   * @access private
   *
   * @param ListInspectionsQuery $query the requested collection filters
   *
   * @return InspectionListCriteria the normalized collection criteria
   */
  private function criteria(ListInspectionsQuery $query): InspectionListCriteria
  {
    try {
      $equipmentId = null !== $query->equipmentId ? (string) InspectionEquipmentId::fromString($query->equipmentId) : null;
      $facilityId = null !== $query->facilityId ? (string) InspectionFacilityId::fromString($query->facilityId) : null;
      $result = null !== $query->result ? InspectionResult::from($query->result)->value : null;
      $status = null !== $query->status ? InspectionStatus::from($query->status)->value : null;
      $performedAtFrom = $this->date($query->performedAtFrom);
      $performedAtTo = $this->date($query->performedAtTo);
      $inspectorUserId = null !== $query->inspectorUserId ? (string) new Uuid($query->inspectorUserId) : null;
      $checklistId = null !== $query->checklistId ? (string) InspectionChecklistId::fromString($query->checklistId) : null;
    } catch (ValueError|Exception $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    $this->assertDateRange($performedAtFrom, $performedAtTo);

    return new InspectionListCriteria(
      subject: new InspectionSubjectCriteria($equipmentId, $query->includeDescendants ? null : $facilityId, $checklistId, $this->parkCandidates($query)),
      execution: new InspectionExecutionCriteria(
        $result,
        $status,
        $performedAtFrom?->format(DateTimeImmutable::ATOM),
        $performedAtTo?->format(DateTimeImmutable::ATOM),
      ),
      inspector: new InspectionInspectorCriteria(userId: $inspectorUserId),
      search: $query->search,
    );

  }

  /**
   * Method date
   *
   * Parses an optional inclusive date filter.
   *
   * @access private
   *
   * @param ?string $value the optional date value
   *
   * @return ?DateTimeImmutable the parsed date
   */
  private function date(?string $value): ?DateTimeImmutable
  {
    return null === $value ? null : new DateTimeImmutable($value);
  }

  /**
   * Method assertDateRange
   *
   * Rejects reversed bounds after all individual filters have been parsed.
   *
   * @access private
   *
   * @param ?DateTimeImmutable $from the inclusive lower bound
   * @param ?DateTimeImmutable $to the inclusive upper bound
   *
   * @return void
   */
  private function assertDateRange(?DateTimeImmutable $from, ?DateTimeImmutable $to): void
  {
    if (null !== $from && null !== $to && $from > $to) {
      throw InvalidValueException::because('performedAtFrom cannot be after performedAtTo.');
    }
  }

  /**
   * @param list<Inspection> $inspections
   *
   * @return list<GetInspectionResult>
   */
  private function mapResults(array $inspections): array
  {
    $results = [];
    $profiles = $this->profiles($inspections);
    $inspectionIds = [];
    foreach ($inspections as $inspection) {
      $inspectionIds[] = (string) $inspection->id();
    }
    $countsByInspectionId = $this->nonConformityRepository->countsByInspectionIds($inspectionIds);

    // Resolved once for the whole page. One lookup per row is how a listing of
    // twenty inspections becomes forty extra queries.
    $equipmentIds = [];
    $facilityIds = [];
    foreach ($inspections as $inspection) {
      $equipmentIds[(string) $inspection->equipmentId()] = true;
      $facilityId = $inspection->facilityId()?->__toString();
      if (null !== $facilityId) {
        $facilityIds[$facilityId] = true;
      }
    }

    $checklistIds = [];
    foreach ($inspections as $inspection) {
      $checklistId = $inspection->checklistId()?->__toString();
      if (null !== $checklistId) {
        $checklistIds[$checklistId] = true;
      }
    }

    $checklistNames = $this->checklistRepository->findNamesByIds(array_keys($checklistIds));
    $serialNumbers = $this->equipmentNaming->findSerialNumbersByIds(array_keys($equipmentIds));
    $facilityNames = $this->facilityNaming->findNamesByIds(array_keys($facilityIds));

    foreach ($inspections as $inspection) {
      $nonConformitiesCount = $countsByInspectionId[(string) $inspection->id()] ?? 0;

      $results[] = new GetInspectionResult(
        inspectorProfile: $profiles[$inspection->inspector()->userId ?? ''] ?? null,
        inspectionId: (string) $inspection->id(),
        organizationId: (string) $inspection->organizationId(),
        equipmentId: (string) $inspection->equipmentId(),
        facilityId: $inspection->facilityId()?->__toString(),
        result: $inspection->result()->value,
        status: $inspection->status()->value,
        performedAt: $inspection->performedAt()->format('c'),
        inspectorType: $inspection->inspector()->type->value,
        inspectorName: $inspection->inspector()->name,
        inspectorUserId: $inspection->inspector()->userId,
        inspectorOrganizationName: $inspection->inspector()->organizationName,
        checklistId: $inspection->checklistId()?->__toString(),
        notes: $inspection->notes(),
        signature: $inspection->signature(),
        nonConformitiesCount: $nonConformitiesCount,
        createdAt: $inspection->createdAt(),
        updatedAt: $inspection->updatedAt(),
        equipmentSerialNumber: $serialNumbers[(string) $inspection->equipmentId()] ?? null,
        facilityName: null !== $inspection->facilityId()
          ? ($facilityNames[(string) $inspection->facilityId()] ?? null)
          : null,
        checklistName: null !== $inspection->checklistId()
          ? ($checklistNames[(string) $inspection->checklistId()] ?? null)
          : null,
      );
    }

    return $results;
  }

  /**
   * Method profiles
   *
   * Resolves inspector snapshots once for the page and retains the optional enrichment fallback.
   *
   * @access private
   *
   * @param list<Inspection> $inspections the current page
   *
   * @return array<string, UserView> the profiles indexed by inspector identifier
   */
  private function profiles(array $inspections): array
  {
    $userIds = [];
    foreach ($inspections as $inspection) {
      if (null !== $inspection->inspector()->userId) {
        $userIds[$inspection->inspector()->userId] = true;
      }
    }

    try {
      return [] === $userIds ? [] : ($this->users?->findByIds(array_keys($userIds)) ?? []);
    } catch (Throwable) {
      return [];
    }
  }

  /**
   * @since 1.1.0
   *
   * @return ?list<string>
   */
  private function parkCandidates(ListInspectionsQuery $query): ?array
  {
    if (null === $query->family && null === $query->customerId && !$query->includeDescendants) {
      return null;
    }
    if ($query->includeDescendants && null === $query->facilityId) {
      throw InvalidValueException::because('includeDescendants requires a facilityId.');
    }
    if (null === $this->parkScopes) {
      throw new LogicException('Published parc scope is unavailable.');
    }
    $candidates = $this->inspectionRepository->findPublishedEquipmentIds($query->organizationId);
    // Validate explicit facility scope even when the requested collection remains direct.
    if (null !== $query->facilityId && !$query->includeDescendants) {
      $this->parkScopes->filterIds($query->organizationId, [], facilityId: $query->facilityId);
    }

    return $this->parkScopes->filterIds($query->organizationId, $candidates, $query->family, $query->customerId, $query->includeDescendants ? $query->facilityId : null);
  }
  // #endregion
}
