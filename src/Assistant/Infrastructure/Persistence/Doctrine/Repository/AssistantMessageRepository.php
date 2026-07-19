<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Persistence\Doctrine\Repository;

use Assistant\Application\Port\Outbound\AssistantMessageRepositoryPort;
use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\ValueObject\AssistantMessageId;
use Assistant\Infrastructure\Persistence\Doctrine\Mapper\AssistantMessageMapper;
use Assistant\Infrastructure\Persistence\Doctrine\Record\{AssistantMessageRecord, AssistantThreadRecord};
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};

use function array_map;

/**
 * Repository AssistantMessageRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantMessageRepository implements AssistantMessageRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<AssistantMessageRecord>
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
    $this->repository = $this->entityManager->getRepository(AssistantMessageRecord::class);
  }
  // #endregion

  // #region Methods
  public function save(AssistantMessage $message): void
  {
    $record = $this->repository->find((string) $message->id());
    $isNew = !$record instanceof AssistantMessageRecord;

    if ($isNew) {
      $record = new AssistantMessageRecord();
      $record->thread = $this->entityManager->getReference(AssistantThreadRecord::class, $message->threadId());
    }

    // The id (an assigned, non-generated identifier) must be populated
    // BEFORE `persist()` is called: Doctrine registers a NONE-strategy
    // entity into the identity map immediately on persist(), not deferred
    // to flush().
    AssistantMessageMapper::toRecord($message, $record);

    if ($isNew) {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  public function findById(AssistantMessageId $id): ?AssistantMessage
  {
    $record = $this->repository->find((string) $id);

    return $record instanceof AssistantMessageRecord ? AssistantMessageMapper::toDomain($record) : null;
  }

  public function listByThread(string $threadId, int $limit, int $offset): array
  {
    // Alias `m` — never `member`, a reserved DQL keyword. `IDENTITY()`
    // compares the raw foreign key without a join.
    /** @var list<AssistantMessageRecord> $records */
    $records = $this->repository->createQueryBuilder('m')
      ->where('IDENTITY(m.thread) = :threadId')
      ->setParameter('threadId', $threadId)
      ->orderBy('m.createdAt', 'ASC')
      ->addOrderBy('m.id', 'ASC')
      ->setFirstResult($offset)
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();

    return array_map(AssistantMessageMapper::toDomain(...), $records);
  }

  public function countByThread(string $threadId): int
  {
    return (int) $this->repository->createQueryBuilder('m')
      ->select('COUNT(m.id)')
      ->where('IDENTITY(m.thread) = :threadId')
      ->setParameter('threadId', $threadId)
      ->getQuery()
      ->getSingleScalarResult();
  }
  // #endregion
}
