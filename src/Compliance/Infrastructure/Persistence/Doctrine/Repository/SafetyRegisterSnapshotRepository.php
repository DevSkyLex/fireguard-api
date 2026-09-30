<?php

declare(strict_types=1);

namespace Compliance\Infrastructure\Persistence\Doctrine\Repository;

use Compliance\Application\Port\Outbound\SafetyRegisterSnapshotRepositoryPort;
use Compliance\Domain\Model\Snapshot\SafetyRegisterSnapshot;
use Compliance\Domain\ValueObject\SafetyRegisterSnapshotId;
use Compliance\Infrastructure\Persistence\Doctrine\Mapper\SafetyRegisterSnapshotMapper;
use Compliance\Infrastructure\Persistence\Doctrine\Record\SafetyRegisterSnapshotRecord;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};

use function array_map;

/**
 * Class SafetyRegisterSnapshotRepository
 *
 * Persists and retrieves organization-scoped archived safety register snapshots.
 *
 * Persists archived safety register snapshots on the **main** database
 * (`compliance_register_snapshots`). Wired with the explicit
 * `doctrine.orm.main_entity_manager` in `config/modules/compliance.yaml`.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SafetyRegisterSnapshotRepository implements SafetyRegisterSnapshotRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<SafetyRegisterSnapshotRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Creates the repository and prepares the snapshot record repository.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   *
   * @return void
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(SafetyRegisterSnapshotRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save
   *
   * Persists a new immutable snapshot record and flushes it to the main database.
   *
   * @access public
   *
   * @param SafetyRegisterSnapshot $snapshot snapshot aggregate to archive
   *
   * @return void
   */
  public function save(SafetyRegisterSnapshot $snapshot): void
  {
    $record = new SafetyRegisterSnapshotRecord();
    SafetyRegisterSnapshotMapper::toRecord($snapshot, $record);

    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  /**
   * Method findForOrganization
   *
   * Finds a snapshot only when both its identifier and organization match.
   *
   * @access public
   *
   * @param SafetyRegisterSnapshotId $id snapshot identifier
   * @param string $organizationId organization scope
   *
   * @return SafetyRegisterSnapshot|null snapshot aggregate, or null when outside the scope or absent
   */
  public function findForOrganization(SafetyRegisterSnapshotId $id, string $organizationId): ?SafetyRegisterSnapshot
  {
    $record = $this->repository->findOneBy([
      'id' => (string) $id,
      'organizationId' => $organizationId,
    ]);

    return $record instanceof SafetyRegisterSnapshotRecord ? SafetyRegisterSnapshotMapper::toDomain($record) : null;
  }

  /**
   * Method listByOrganization
   *
   * Lists an organization’s snapshots newest first with offset pagination.
   *
   * @access public
   *
   * @param string $organizationId organization scope
   * @param int $limit maximum number of snapshots
   * @param int $offset zero-based row offset
   *
   * @return list<SafetyRegisterSnapshot> snapshot aggregates
   */
  public function listByOrganization(string $organizationId, int $limit, int $offset): array
  {
    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('s')
      ->from(SafetyRegisterSnapshotRecord::class, 's')
      ->where('s.organizationId = :organizationId')
      ->setParameter('organizationId', $organizationId)
      ->orderBy('s.generatedAt', 'DESC')
      ->addOrderBy('s.id', 'DESC')
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    /** @var list<SafetyRegisterSnapshotRecord> $records */
    $records = $queryBuilder->getQuery()->getResult();

    return array_map(SafetyRegisterSnapshotMapper::toDomain(...), $records);
  }

  /**
   * Method countByOrganization
   *
   * Counts archived snapshots belonging to one organization.
   *
   * @access public
   *
   * @param string $organizationId organization scope
   *
   * @return int number of snapshots in the scope
   */
  public function countByOrganization(string $organizationId): int
  {
    return (int) $this->entityManager->createQueryBuilder()
      ->select('COUNT(s.id)')
      ->from(SafetyRegisterSnapshotRecord::class, 's')
      ->where('s.organizationId = :organizationId')
      ->setParameter('organizationId', $organizationId)
      ->getQuery()
      ->getSingleScalarResult();
  }
  // #endregion
}
