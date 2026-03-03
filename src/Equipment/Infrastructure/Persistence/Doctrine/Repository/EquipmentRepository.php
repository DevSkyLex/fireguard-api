<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Equipment\Application\Port\Outbound\EquipmentRepositoryPort;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId};
use Equipment\Infrastructure\Persistence\Doctrine\Mapper\EquipmentMapper;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;

use function array_map;

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
    $existing = $this->repository->find($record->id);

    if ($existing instanceof EquipmentRecord) {
      $existing->organizationId = $record->organizationId;
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

    $this->entityManager->flush();
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
  ): array {
    $criteria = ['organizationId' => (string) $organizationId];

    if (null !== $facilityId) {
      $criteria['facilityId'] = $facilityId;
    }

    if (null !== $type) {
      $criteria['type'] = $type;
    }

    if (null !== $status) {
      $criteria['status'] = $status;
    }

    $records = $this->repository->findBy($criteria, ['createdAt' => 'DESC']);

    return array_map(
      static fn (EquipmentRecord $record): Equipment => EquipmentMapper::toDomain($record),
      $records,
    );
  }

  // #endregion
}
