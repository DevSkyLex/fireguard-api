<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Persistence\Doctrine\Repository;

use Assistant\Application\Port\Outbound\AssistantThreadRepositoryPort;
use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\ValueObject\AssistantThreadId;
use Assistant\Infrastructure\Persistence\Doctrine\Mapper\AssistantThreadMapper;
use Assistant\Infrastructure\Persistence\Doctrine\Record\AssistantThreadRecord;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};

use function array_map;

/**
 * Repository AssistantThreadRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantThreadRepository implements AssistantThreadRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<AssistantThreadRecord>
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
    $this->repository = $this->entityManager->getRepository(AssistantThreadRecord::class);
  }
  // #endregion

  // #region Methods
  public function save(AssistantThread $thread): void
  {
    $record = $this->repository->find((string) $thread->id());
    $isNew = !$record instanceof AssistantThreadRecord;

    if ($isNew) {
      $record = new AssistantThreadRecord();
    }

    // The id (an assigned, non-generated identifier) must be populated
    // BEFORE `persist()` is called: Doctrine registers a NONE-strategy
    // entity into the identity map immediately on persist(), not deferred
    // to flush().
    AssistantThreadMapper::toRecord($thread, $record);

    if ($isNew) {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  public function findById(AssistantThreadId $id): ?AssistantThread
  {
    $record = $this->repository->find((string) $id);

    return $record instanceof AssistantThreadRecord ? AssistantThreadMapper::toDomain($record) : null;
  }

  public function listByOrganizationAndMember(string $organizationId, string $memberId, int $limit, int $offset): array
  {
    // Alias `t` — never `member`, a reserved DQL keyword.
    /** @var list<AssistantThreadRecord> $records */
    $records = $this->repository->createQueryBuilder('t')
      ->where('t.organizationId = :organizationId')
      ->andWhere('t.memberId = :memberId')
      ->setParameter('organizationId', $organizationId)
      ->setParameter('memberId', $memberId)
      ->orderBy('t.updatedAt', 'DESC')
      ->addOrderBy('t.id', 'DESC')
      ->setFirstResult($offset)
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();

    return array_map(AssistantThreadMapper::toDomain(...), $records);
  }

  public function countByOrganizationAndMember(string $organizationId, string $memberId): int
  {
    return (int) $this->repository->createQueryBuilder('t')
      ->select('COUNT(t.id)')
      ->where('t.organizationId = :organizationId')
      ->andWhere('t.memberId = :memberId')
      ->setParameter('organizationId', $organizationId)
      ->setParameter('memberId', $memberId)
      ->getQuery()
      ->getSingleScalarResult();
  }
  // #endregion
}
