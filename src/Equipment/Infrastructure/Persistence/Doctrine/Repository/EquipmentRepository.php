<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Equipment\Application\Port\Outbound\EquipmentRepositoryPort;
use Equipment\Domain\Exception\EquipmentSerialNumberAlreadyExistsException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId};
use Equipment\Infrastructure\Persistence\Doctrine\Mapper\EquipmentMapper;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Throwable;

use function addcslashes;
use function array_map;
use function mb_strtolower;
use function str_contains;
use function strtolower;
use function strtoupper;

/**
 * Repository EquipmentRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentRepository implements EquipmentRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<EquipmentRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(EquipmentRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * @since 1.0.0
   */
  public function save(Equipment $equipment): void
  {
    $record = EquipmentMapper::toRecord($equipment);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $equipment->organizationId());
    $record->organization = $organization;
    $existing = $this->repository->find($record->id);

    if ($existing instanceof EquipmentRecord) {
      $existing->organization = $organization;
      $existing->facilityId = $record->facilityId;
      $existing->type = $record->type;
      $existing->subType = $record->subType;
      $existing->brand = $record->brand;
      $existing->model = $record->model;
      $existing->serialNumber = $record->serialNumber;
      $existing->locationLabel = $record->locationLabel;
      $existing->status = $record->status;
      $existing->installedAt = $record->installedAt;
      $existing->commissionedAt = $record->commissionedAt;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    try {
      $this->entityManager->flush();
    } catch (Throwable $exception) {
      if ($this->isDuplicateSerialNumberViolation($exception)) {
        throw EquipmentSerialNumberAlreadyExistsException::withSerialNumber($equipment->serialNumber() ?? '');
      }

      throw $exception;
    }
  }

  /**
   * Method findById.
   *
   * @since 1.0.0
   */
  public function findById(EquipmentId $id): ?Equipment
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof EquipmentRecord) {
      return null;
    }

    return EquipmentMapper::toDomain($record);
  }

  /**
   * Method findByOrganizationId.
   *
   * @since 1.0.0
   */
  public function findByOrganizationId(
    EquipmentOrganizationId $organizationId,
    ?string $facilityId = null,
    ?string $type = null,
    ?string $status = null,
    ?string $brand = null,
    ?string $model = null,
    ?string $subType = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('createdAt', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
  ): array {
    $queryBuilder = $this->createListQueryBuilder(
      $organizationId,
      $facilityId,
      $type,
      $status,
      $brand,
      $model,
      $subType,
      $search,
    );

    $sortField = match ($sorting->field) {
      'type' => 'e.type',
      'status' => 'e.status',
      'brand' => 'e.brand',
      'model' => 'e.model',
      default => 'e.createdAt',
    };

    /** @var list<EquipmentRecord> $records */
    $records = $queryBuilder
      ->orderBy($sortField, strtoupper($sorting->direction->value))
      ->addOrderBy('e.id', 'ASC')
      ->setFirstResult($offset)
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();

    return array_map(
      static fn (EquipmentRecord $record): Equipment => EquipmentMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method countByOrganizationId.
   *
   * @since 1.0.0
   */
  public function countByOrganizationId(
    EquipmentOrganizationId $organizationId,
    ?string $facilityId = null,
    ?string $type = null,
    ?string $status = null,
    ?string $brand = null,
    ?string $model = null,
    ?string $subType = null,
    ?string $search = null,
  ): int {
    return (int) $this->createListQueryBuilder(
      $organizationId,
      $facilityId,
      $type,
      $status,
      $brand,
      $model,
      $subType,
      $search,
    )
      ->select('COUNT(e.id)')
      ->getQuery()
      ->getSingleScalarResult();
  }

  public function countByStatusForOrganizationId(EquipmentOrganizationId $organizationId): array
  {
    /** @var list<array{status: string, equipmentCount: int|string}> $rows */
    $rows = $this->createListQueryBuilder(
      $organizationId,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
    )
      ->select('e.status AS status, COUNT(e.id) AS equipmentCount')
      ->groupBy('e.status')
      ->getQuery()
      ->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['status']] = (int) $row['equipmentCount'];
    }

    return $counts;
  }

  public function countByTypeForOrganizationId(EquipmentOrganizationId $organizationId): array
  {
    /** @var list<array{type: string, equipmentCount: int|string}> $rows */
    $rows = $this->createListQueryBuilder(
      $organizationId,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
    )
      ->select('e.type AS type, COUNT(e.id) AS equipmentCount')
      ->groupBy('e.type')
      ->getQuery()
      ->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['type']] = (int) $row['equipmentCount'];
    }

    return $counts;
  }

  private function createListQueryBuilder(
    EquipmentOrganizationId $organizationId,
    ?string $facilityId,
    ?string $type,
    ?string $status,
    ?string $brand,
    ?string $model,
    ?string $subType,
    ?string $search,
  ): QueryBuilder {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('e')
      ->from(EquipmentRecord::class, 'e')
      ->where('e.organization = :organization')
      ->setParameter('organization', $organization);

    if (null !== $facilityId) {
      $queryBuilder
        ->andWhere('e.facilityId = :facilityId')
        ->setParameter('facilityId', $facilityId);
    }

    if (null !== $type) {
      $queryBuilder
        ->andWhere('e.type = :type')
        ->setParameter('type', $type);
    }

    if (null !== $status) {
      $queryBuilder
        ->andWhere('e.status = :status')
        ->setParameter('status', $status);
    }

    if (null !== $brand) {
      $queryBuilder
        ->andWhere('e.brand = :brand')
        ->setParameter('brand', $brand);
    }

    if (null !== $model) {
      $queryBuilder
        ->andWhere('e.model = :model')
        ->setParameter('model', $model);
    }

    if (null !== $subType) {
      $queryBuilder
        ->andWhere('e.subType = :subType')
        ->setParameter('subType', $subType);
    }

    if (null !== $search && '' !== $search) {
      $normalizedSearch = '%' . addcslashes(mb_strtolower($search), '%_') . '%';

      $queryBuilder
        ->andWhere('(
          LOWER(e.type) LIKE :search OR
          LOWER(COALESCE(e.subType, \'\')) LIKE :search OR
          LOWER(COALESCE(e.brand, \'\')) LIKE :search OR
          LOWER(COALESCE(e.model, \'\')) LIKE :search OR
          LOWER(COALESCE(e.serialNumber, \'\')) LIKE :search OR
          LOWER(e.status) LIKE :search OR
          LOWER(COALESCE(e.locationLabel, \'\')) LIKE :search
        )')
        ->setParameter('search', $normalizedSearch);
    }

    return $queryBuilder;
  }

  private function isDuplicateSerialNumberViolation(Throwable $exception): bool
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof UniqueConstraintViolationException) {
        $message = strtolower($current->getMessage());

        if (str_contains($message, 'uniq_equipment_organization_serial') || (str_contains($message, 'equipment') && str_contains($message, 'serial'))) {
          return true;
        }
      }

      $current = $current->getPrevious();
    }

    return false;
  }

  // #endregion
}
