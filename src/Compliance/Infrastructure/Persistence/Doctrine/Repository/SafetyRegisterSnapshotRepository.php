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
 * Repository SafetyRegisterSnapshotRepository.
 *
 * Persists archived safety register snapshots on the **main** database
 * (`compliance_register_snapshots`). Wired with the explicit
 * `doctrine.orm.main_entity_manager` in `config/modules/compliance.yaml`.
 *
 * @category Repository
 *
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
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(SafetyRegisterSnapshotRecord::class);
  }
  // #endregion

  // #region Methods
  public function save(SafetyRegisterSnapshot $snapshot): void
  {
    $record = new SafetyRegisterSnapshotRecord();
    SafetyRegisterSnapshotMapper::toRecord($snapshot, $record);

    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  public function findForOrganization(SafetyRegisterSnapshotId $id, string $organizationId): ?SafetyRegisterSnapshot
  {
    $record = $this->repository->findOneBy([
      'id' => (string) $id,
      'organizationId' => $organizationId,
    ]);

    return $record instanceof SafetyRegisterSnapshotRecord ? SafetyRegisterSnapshotMapper::toDomain($record) : null;
  }

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
