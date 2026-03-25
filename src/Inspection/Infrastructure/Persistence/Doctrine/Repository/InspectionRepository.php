<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Inspection\Application\Port\Outbound\InspectionRepositoryPort;
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId};
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\InspectionMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

use function addcslashes;
use function array_map;
use function mb_strtolower;
use function strtoupper;

final readonly class InspectionRepository implements InspectionRepositoryPort
{
  /**
   * @var EntityRepository<InspectionRecord>
   */
  private EntityRepository $repository;

  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(InspectionRecord::class);
  }

  public function save(Inspection $inspection): void
  {
    $record = InspectionMapper::toRecord($inspection);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $inspection->organizationId());
    $record->organization = $organization;
    $existing = $this->repository->find($record->id);

    if ($existing instanceof InspectionRecord) {
      $existing->organization = $organization;
      $existing->equipmentId = $record->equipmentId;
      $existing->facilityId = $record->facilityId;
      $existing->inspectorType = $record->inspectorType;
      $existing->inspectorName = $record->inspectorName;
      $existing->inspectorUserId = $record->inspectorUserId;
      $existing->inspectorOrganizationName = $record->inspectorOrganizationName;
      $existing->result = $record->result;
      $existing->status = $record->status;
      $existing->performedAt = $record->performedAt;
      $existing->checklistId = $record->checklistId;
      $existing->notes = $record->notes;
      $existing->signature = $record->signature;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  public function remove(Inspection $inspection): void
  {
    $record = $this->repository->find((string) $inspection->id());

    if (!$record instanceof InspectionRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }

  public function findById(InspectionId $id): ?Inspection
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InspectionRecord) {
      return null;
    }

    return InspectionMapper::toDomain($record);
  }

  public function findByOrganizationId(
    InspectionOrganizationId $organizationId,
    ?string $equipmentId = null,
    ?string $facilityId = null,
    ?string $result = null,
    ?string $status = null,
    ?string $performedAtFrom = null,
    ?string $performedAtTo = null,
    ?string $inspectorUserId = null,
    ?string $checklistId = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('createdAt', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
  ): array {
    /** @var list<InspectionRecord> $records */
    $records = $this->createListQueryBuilder(
      $organizationId,
      $equipmentId,
      $facilityId,
      $result,
      $status,
      $performedAtFrom,
      $performedAtTo,
      $inspectorUserId,
      $checklistId,
      $search,
    )
      ->orderBy($this->resolveSortField($sorting->field), strtoupper($sorting->direction->value))
      ->addOrderBy('i.id', 'ASC')
      ->setFirstResult($offset)
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();

    return array_map(
      static fn (InspectionRecord $record): Inspection => InspectionMapper::toDomain($record),
      $records,
    );
  }

  public function countByOrganizationId(
    InspectionOrganizationId $organizationId,
    ?string $equipmentId = null,
    ?string $facilityId = null,
    ?string $result = null,
    ?string $status = null,
    ?string $performedAtFrom = null,
    ?string $performedAtTo = null,
    ?string $inspectorUserId = null,
    ?string $checklistId = null,
    ?string $search = null,
  ): int {
    return (int) $this->createListQueryBuilder(
      $organizationId,
      $equipmentId,
      $facilityId,
      $result,
      $status,
      $performedAtFrom,
      $performedAtTo,
      $inspectorUserId,
      $checklistId,
      $search,
    )
      ->select('COUNT(i.id)')
      ->getQuery()
      ->getSingleScalarResult();
  }

  private function createListQueryBuilder(
    InspectionOrganizationId $organizationId,
    ?string $equipmentId,
    ?string $facilityId,
    ?string $result,
    ?string $status,
    ?string $performedAtFrom,
    ?string $performedAtTo,
    ?string $inspectorUserId,
    ?string $checklistId,
    ?string $search,
  ): QueryBuilder {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('i')
      ->from(InspectionRecord::class, 'i')
      ->where('i.organization = :organization')
      ->setParameter('organization', $organization);

    if (null !== $equipmentId) {
      $queryBuilder
        ->andWhere('i.equipmentId = :equipmentId')
        ->setParameter('equipmentId', $equipmentId);
    }

    if (null !== $facilityId) {
      $queryBuilder
        ->andWhere('i.facilityId = :facilityId')
        ->setParameter('facilityId', $facilityId);
    }

    if (null !== $result) {
      $queryBuilder
        ->andWhere('i.result = :result')
        ->setParameter('result', $result);
    }

    if (null !== $status) {
      $queryBuilder
        ->andWhere('i.status = :status')
        ->setParameter('status', $status);
    }

    if (null !== $performedAtFrom) {
      $queryBuilder
        ->andWhere('i.performedAt >= :performedAtFrom')
        ->setParameter('performedAtFrom', $performedAtFrom);
    }

    if (null !== $performedAtTo) {
      $queryBuilder
        ->andWhere('i.performedAt <= :performedAtTo')
        ->setParameter('performedAtTo', $performedAtTo);
    }

    if (null !== $inspectorUserId) {
      $queryBuilder
        ->andWhere('i.inspectorUserId = :inspectorUserId')
        ->setParameter('inspectorUserId', $inspectorUserId);
    }

    if (null !== $checklistId) {
      $queryBuilder
        ->andWhere('i.checklistId = :checklistId')
        ->setParameter('checklistId', $checklistId);
    }

    if (null !== $search && '' !== $search) {
      $normalizedSearch = '%' . addcslashes(mb_strtolower($search), '%_') . '%';

      $queryBuilder
        ->andWhere('(
          LOWER(i.result) LIKE :search OR
          LOWER(i.status) LIKE :search OR
          LOWER(i.inspectorName) LIKE :search OR
          LOWER(i.equipmentId) LIKE :search OR
          LOWER(COALESCE(i.facilityId, \'\')) LIKE :search OR
          LOWER(COALESCE(i.checklistId, \'\')) LIKE :search
        )')
        ->setParameter('search', $normalizedSearch);
    }

    return $queryBuilder;
  }

  private function resolveSortField(string $field): string
  {
    return match ($field) {
      'result' => 'i.result',
      'status' => 'i.status',
      'performedAt' => 'i.performedAt',
      default => 'i.createdAt',
    };
  }
}
