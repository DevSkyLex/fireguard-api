<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Inspection\Application\Port\Outbound\CanonicalInspectionRepositoryPort;
use Inspection\Domain\Model\Inspection\CanonicalInspection;
use Inspection\Domain\ValueObject\InspectionId;
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\CanonicalInspectionMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;

/**
 * Repository CanonicalInspectionRepository.
 *
 * **No timezone normalisation, on purpose.** `InspectionRepository` pushes
 * `performedAt`/`createdAt`/`updatedAt` through `DATABASE_STORAGE_TIMEZONE`
 * on the way in and out; the canonical write path never did — the processor
 * assigned a bare `new DateTimeImmutable()` straight onto the record. Adding
 * the normalisation here would silently shift every canonically-written
 * `updated_at` wherever the storage timezone differs from PHP's. The
 * inconsistency is real and pre-existing; it is recorded in
 * `src/Inspection/MODULE.md` rather than fixed as a side effect.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CanonicalInspectionRepository implements CanonicalInspectionRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<InspectionRecord>
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
    $this->repository = $this->entityManager->getRepository(InspectionRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method findById.
   *
   * @since 1.0.0
   */
  public function findById(InspectionId $id): ?CanonicalInspection
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InspectionRecord) {
      return null;
    }

    return CanonicalInspectionMapper::toDomain($record);
  }

  /**
   * Method save.
   *
   * @since 1.0.0
   */
  public function save(CanonicalInspection $inspection): void
  {
    $record = $this->repository->find((string) $inspection->id());

    if (!$record instanceof InspectionRecord) {
      return;
    }

    CanonicalInspectionMapper::applyTo($inspection, $record);
    $this->entityManager->flush();
  }

  /**
   * Method delete.
   *
   * @since 1.0.0
   */
  public function delete(InspectionId $id): void
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InspectionRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }
  // #endregion
}
