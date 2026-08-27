<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Inspection\Application\Contract\Inspection\CanonicalInspectionReadView;
use Inspection\Application\Port\Outbound\CanonicalInspectionRepositoryPort;
use Inspection\Domain\Model\Inspection\CanonicalInspection;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId};
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\CanonicalInspectionMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;

use function array_map;

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
   * Method findReadById.
   *
   * @since 1.0.0
   */
  public function findReadById(InspectionId $id): ?CanonicalInspectionReadView
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InspectionRecord) {
      return null;
    }

    return CanonicalInspectionMapper::toReadView($record);
  }

  /**
   * Method findReadByFilters.
   *
   * @since 1.0.0
   */
  public function findReadByFilters(
    InspectionOrganizationId $organizationId,
    ?string $interventionId,
    ?string $equipmentId,
    string $recordStatus,
    int $limit,
    int $offset,
  ): array {
    /** @var list<InspectionRecord> $records */
    $records = $this
      ->filtered($organizationId, $interventionId, $equipmentId, $recordStatus)
      ->select('i')
      ->orderBy('i.createdAt', 'ASC')
      ->setFirstResult($offset)
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();

    return array_map(
      static fn (InspectionRecord $record): CanonicalInspectionReadView => CanonicalInspectionMapper::toReadView($record),
      $records,
    );
  }

  /**
   * Method countReadByFilters.
   *
   * @since 1.0.0
   */
  public function countReadByFilters(
    InspectionOrganizationId $organizationId,
    ?string $interventionId,
    ?string $equipmentId,
    string $recordStatus,
  ): int {
    return (int) $this
      ->filtered($organizationId, $interventionId, $equipmentId, $recordStatus)
      ->select('COUNT(i.id)')
      ->getQuery()
      ->getSingleScalarResult();
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

  /**
   * Method filtered.
   *
   * The one place the collection's WHERE clause is written, so the count and
   * the page can never drift apart.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the owning organization
   * @param ?string $interventionId narrow to one intervention, or null
   * @param ?string $equipmentId narrow to one equipment item, or null
   * @param string $recordStatus the representation lifecycle status
   *
   * @return QueryBuilder the shared filter clause
   */
  private function filtered(
    InspectionOrganizationId $organizationId,
    ?string $interventionId,
    ?string $equipmentId,
    string $recordStatus,
  ): QueryBuilder {
    $query = $this->entityManager->createQueryBuilder()
      ->from(InspectionRecord::class, 'i')
      ->where('i.organization = :organization')
      ->setParameter('organization', (string) $organizationId)
      ->andWhere('i.recordStatus = :recordStatus')
      ->setParameter('recordStatus', $recordStatus);

    if (null !== $interventionId) {
      $query->andWhere('i.interventionId = :interventionId')->setParameter('interventionId', $interventionId);
    }

    if (null !== $equipmentId) {
      $query->andWhere('i.equipmentId = :equipmentId')->setParameter('equipmentId', $equipmentId);
    }

    return $query;
  }
  // #endregion
}
