<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Equipment\Application\Port\Outbound\CanonicalEquipmentRepositoryPort;
use Equipment\Domain\Model\Equipment\CanonicalEquipment;
use Equipment\Domain\ValueObject\EquipmentId;
use Equipment\Infrastructure\Persistence\Doctrine\Mapper\CanonicalEquipmentMapper;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;

/**
 * Repository CanonicalEquipmentRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CanonicalEquipmentRepository implements CanonicalEquipmentRepositoryPort
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
   * @param EntityManagerInterface $entityManager the `main` entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(EquipmentRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method findById.
   *
   * @since 1.0.0
   */
  public function findById(EquipmentId $id): ?CanonicalEquipment
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof EquipmentRecord) {
      return null;
    }

    return CanonicalEquipmentMapper::toDomain($record);
  }

  /**
   * Method save.
   *
   * @since 1.0.0
   */
  public function save(CanonicalEquipment $equipment): void
  {
    $record = $this->repository->find((string) $equipment->id());

    if (!$record instanceof EquipmentRecord) {
      return;
    }

    CanonicalEquipmentMapper::applyTo($equipment, $record);
    $this->entityManager->flush();
  }

  /**
   * Method delete.
   *
   * @since 1.0.0
   */
  public function delete(EquipmentId $id): void
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof EquipmentRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }
  // #endregion
}
