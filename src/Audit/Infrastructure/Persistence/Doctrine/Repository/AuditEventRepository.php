<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Persistence\Doctrine\Repository;

use Audit\Application\Contract\{AuditEventSearchCriteria, AuditEventView};
use Audit\Application\Port\Outbound\AuditEventRepositoryPort;
use Audit\Domain\Model\AuditEvent;
use Audit\Infrastructure\Persistence\Doctrine\Record\AuditEventRecord;
use Audit\Infrastructure\Service\AuditHashService;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Symfony\Component\Uid\Uuid;

use function array_map;
use function is_array;
use function is_numeric;
use function is_string;
use function str_replace;

/**
 * Repository AuditEventRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuditEventRepository implements AuditEventRepositoryPort
{
  /**
   * @var EntityRepository<AuditEventRecord>
   */
  private EntityRepository $repository;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AuditEventRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager
   * @param AuditHashService $hashService the hash service
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly AuditHashService $hashService,
  ) {
    $this->repository = $entityManager->getRepository(className: AuditEventRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method append.
   *
   * Appends an audit event to the ledger, computing
   * the chain sequence and event hash.
   *
   * @since 1.0.0
   *
   * @param AuditEvent $event the audit event
   *
   * @return AuditEvent the persisted event with chain metadata
   */
  public function append(AuditEvent $event): AuditEvent
  {
    $recordedAt = new DateTimeImmutable();
    $chainId = $this->resolveChainId($event->tenantId);

    return $this->entityManager->wrapInTransaction(function () use ($event, $recordedAt, $chainId) {
      $connection = $this->entityManager->getConnection();
      $this->ensureChainRow($connection, $chainId, $recordedAt);

      $chainRow = $this->lockChainRow($connection, $chainId);
      $prevHash = null;
      if (isset($chainRow['last_hash']) && is_string($chainRow['last_hash'])) {
        $prevHash = $chainRow['last_hash'];
      }
      $lastSequence = 0;
      if (isset($chainRow['last_sequence']) && is_numeric($chainRow['last_sequence'])) {
        $lastSequence = (int) $chainRow['last_sequence'];
      }
      $sequence = $lastSequence + 1;

      // organization_id is deliberately absent from the hash payload: the
      // column is a denormalized filter copy of metadata['organization_id'],
      // which is already hash-covered. Keeping the payload recipe unchanged
      // keeps every row (pre- and post-backfill) verifiable the same way.
      $payload = [
        'id' => $event->id->value,
        'chain_id' => $chainId,
        'sequence' => $sequence,
        'action' => $event->action,
        'actor_type' => $event->actorType,
        'actor_id' => $event->actorId,
        'actor_email' => $event->actorEmail,
        'actor_email_hash' => $event->actorEmailHash,
        'subject_type' => $event->subjectType,
        'subject_id' => $event->subjectId,
        'client_id' => $event->clientId,
        'tenant_id' => $event->tenantId,
        'ip_address' => $event->ipAddress,
        'ip_hash' => $event->ipHash,
        'user_agent' => $event->userAgent,
        'metadata' => $event->metadata,
        'occurred_at' => $event->occurredAt->format(DateTimeInterface::ATOM),
        'recorded_at' => $recordedAt->format(DateTimeInterface::ATOM),
      ];

      $eventHash = $this->hashService->compute(
        prevHash: $prevHash ?? 'GENESIS',
        payload: $payload,
      );

      $record = new AuditEventRecord();
      $record->id = Uuid::fromString($event->id->value);
      $record->chainId = $chainId;
      $record->sequence = $sequence;
      $record->action = $event->action;
      $record->actorType = $event->actorType;
      $record->actorId = $event->actorId;
      $record->actorEmail = $event->actorEmail;
      $record->actorEmailHash = $event->actorEmailHash;
      $record->subjectType = $event->subjectType;
      $record->subjectId = $event->subjectId;
      $record->clientId = $event->clientId;
      $record->tenantId = $event->tenantId;
      $record->organizationId = $event->organizationId;
      $record->ipAddress = $event->ipAddress;
      $record->ipHash = $event->ipHash;
      $record->userAgent = $event->userAgent;
      $record->metadata = $event->metadata;
      $record->occurredAt = $event->occurredAt;
      $record->recordedAt = $recordedAt;
      $record->prevHash = $prevHash;
      $record->eventHash = $eventHash;

      $this->entityManager->persist(object: $record);
      $this->entityManager->flush();

      $connection->executeStatement(
        'UPDATE audit_event_chains SET last_hash = :last_hash, last_sequence = :last_sequence, updated_at = :updated_at WHERE chain_id = :chain_id',
        [
          'last_hash' => $eventHash,
          'last_sequence' => $sequence,
          'updated_at' => $recordedAt->format('Y-m-d H:i:s'),
          'chain_id' => $chainId,
        ],
      );

      return new AuditEvent(
        id: $event->id,
        action: $event->action,
        actorType: $event->actorType,
        actorId: $event->actorId,
        actorEmail: $event->actorEmail,
        actorEmailHash: $event->actorEmailHash,
        subjectType: $event->subjectType,
        subjectId: $event->subjectId,
        clientId: $event->clientId,
        tenantId: $event->tenantId,
        ipAddress: $event->ipAddress,
        ipHash: $event->ipHash,
        userAgent: $event->userAgent,
        metadata: $event->metadata,
        occurredAt: $event->occurredAt,
        recordedAt: $recordedAt,
        chainId: $chainId,
        sequence: $sequence,
        prevHash: $prevHash,
        eventHash: $eventHash,
        organizationId: $event->organizationId,
      );
    });
  }

  /**
   * Method findById.
   *
   * Fetches a single audit event by ID.
   *
   * @since 1.0.0
   *
   * @param string $id the audit event ID
   *
   * @return AuditEventView|null the event view or null
   */
  public function findById(string $id): ?AuditEventView
  {
    $record = $this->repository->find(id: $id);

    if (!$record instanceof AuditEventRecord) {
      return null;
    }

    return $this->mapToView($record);
  }

  /**
   * Method search.
   *
   * Searches audit events with filters and pagination.
   *
   * @since 1.0.0
   *
   * @param AuditEventSearchCriteria $criteria the search criteria
   * @param Pagination $pagination pagination parameters
   *
   * @return PaginatedResult<AuditEventView> the paginated results
   */
  public function search(AuditEventSearchCriteria $criteria, Pagination $pagination): PaginatedResult
  {
    $qb = $this->entityManager->createQueryBuilder()
      ->select('a')
      ->from(AuditEventRecord::class, 'a');

    $this->applyCriteria($qb, $criteria);

    $countQb = clone $qb;
    $countQb->select('COUNT(a.id)');
    $total = (int) $countQb->getQuery()->getSingleScalarResult();

    // Unique tiebreaker: the sort field above is caller-chosen and rarely
    // unique, so rows tied on it order arbitrarily and LIMIT/OFFSET then
    // repeats some across pages while skipping others.
    $qb->orderBy('a.' . $this->resolveSortField($criteria->sorting->field), $criteria->sorting->direction->value)
      ->addOrderBy('a.id', 'ASC')
      ->setFirstResult($pagination->offset)
      ->setMaxResults($pagination->limit);

    /** @var array<AuditEventRecord> $records */
    $records = $qb->getQuery()->getResult();

    $views = array_map(
      fn (AuditEventRecord $record): AuditEventView => $this->mapToView($record),
      $records,
    );

    return new PaginatedResult(
      items: $views,
      total: $total,
      limit: $pagination->limit,
      offset: $pagination->offset,
    );
  }

  /**
   * Method countMatching.
   *
   * Counts audit events matching a search criteria, with no pagination
   * applied. Reuses the exact same filter DQL as `search()`.
   *
   * @since 1.3.0
   *
   * @param AuditEventSearchCriteria $criteria the search criteria
   *
   * @return int the number of matching audit events
   */
  public function countMatching(AuditEventSearchCriteria $criteria): int
  {
    $qb = $this->entityManager->createQueryBuilder()
      ->select('COUNT(a.id)')
      ->from(AuditEventRecord::class, 'a');

    $this->applyCriteria($qb, $criteria);

    return (int) $qb->getQuery()->getSingleScalarResult();
  }

  /**
   * Method stream.
   *
   * Streams audit events matching a search criteria, with no pagination
   * applied. Uses Doctrine's `toIterable()` so results are hydrated and
   * yielded one row at a time rather than materialized into a single
   * array — the caller (an export) may match tens of thousands of rows.
   *
   * @since 1.3.0
   *
   * @param AuditEventSearchCriteria $criteria the search criteria
   *
   * @return iterable<AuditEventView>
   */
  public function stream(AuditEventSearchCriteria $criteria): iterable
  {
    $qb = $this->entityManager->createQueryBuilder()
      ->select('a')
      ->from(AuditEventRecord::class, 'a');

    $this->applyCriteria($qb, $criteria);

    $qb->orderBy('a.' . $this->resolveSortField($criteria->sorting->field), $criteria->sorting->direction->value);

    foreach ($qb->getQuery()->toIterable() as $record) {
      /** @var AuditEventRecord $record */
      yield $this->mapToView($record);
    }
  }

  /**
   * Method resolveChainId.
   *
   * Resolves the chain ID for the event.
   *
   * @since 1.0.0
   *
   * @param string|null $tenantId the tenant identifier
   *
   * @return string the chain ID
   */
  private function resolveChainId(?string $tenantId): string
  {
    if (null !== $tenantId && '' !== $tenantId) {
      return 'tenant:' . $tenantId;
    }

    return 'global';
  }

  /**
   * Method ensureChainRow.
   *
   * Ensures the chain row exists for the given chain ID.
   *
   * @since 1.0.0
   *
   * @param Connection $connection the DBAL connection
   * @param string $chainId the chain ID
   * @param DateTimeImmutable $now the current timestamp
   */
  private function ensureChainRow(Connection $connection, string $chainId, DateTimeImmutable $now): void
  {
    $connection->executeStatement(
      'INSERT INTO audit_event_chains (chain_id, last_hash, last_sequence, updated_at) VALUES (:chain_id, :last_hash, :last_sequence, :updated_at) ON CONFLICT (chain_id) DO NOTHING',
      [
        'chain_id' => $chainId,
        'last_hash' => 'GENESIS',
        'last_sequence' => 0,
        'updated_at' => $now->format('Y-m-d H:i:s'),
      ],
    );
  }

  /**
   * Method lockChainRow.
   *
   * Locks and returns the current chain state.
   *
   * @since 1.0.0
   *
   * @param Connection $connection the DBAL connection
   * @param string $chainId the chain ID
   *
   * @return array<string, mixed> the chain row data
   */
  private function lockChainRow(Connection $connection, string $chainId): array
  {
    // FOR UPDATE serializes concurrent appends to the same chain: the hash
    // link and sequence number are read here and written by the caller, so
    // the row must stay locked for the whole append.
    $sql = 'SELECT chain_id, last_hash, last_sequence FROM audit_event_chains WHERE chain_id = :chain_id FOR UPDATE';

    $row = $connection->fetchAssociative($sql, ['chain_id' => $chainId]);

    return is_array($row) ? $row : [];
  }

  /**
   * Method applyCriteria.
   *
   * Applies search filters to the query builder.
   *
   * @since 1.0.0
   *
   * @param QueryBuilder $qb the query builder
   * @param AuditEventSearchCriteria $criteria the search criteria
   */
  private function applyCriteria(QueryBuilder $qb, AuditEventSearchCriteria $criteria): void
  {
    if ($criteria->action) {
      $qb->andWhere('a.action = :action')->setParameter('action', $criteria->action);
    }

    if ($criteria->actorType) {
      $qb->andWhere('a.actorType = :actorType')->setParameter('actorType', $criteria->actorType);
    }

    if ($criteria->actorId) {
      $qb->andWhere('a.actorId = :actorId')->setParameter('actorId', $criteria->actorId);
    }

    if ($criteria->actorEmailHash) {
      $qb->andWhere('a.actorEmailHash = :actorEmailHash')->setParameter('actorEmailHash', $criteria->actorEmailHash);
    }

    if ($criteria->subjectType) {
      $qb->andWhere('a.subjectType = :subjectType')->setParameter('subjectType', $criteria->subjectType);
    }

    if ($criteria->subjectId) {
      $qb->andWhere('a.subjectId = :subjectId')->setParameter('subjectId', $criteria->subjectId);
    }

    if ($criteria->clientId) {
      $qb->andWhere('a.clientId = :clientId')->setParameter('clientId', $criteria->clientId);
    }

    if ($criteria->tenantId) {
      $qb->andWhere('a.tenantId = :tenantId')->setParameter('tenantId', $criteria->tenantId);
    }

    if ($criteria->organizationId) {
      $qb->andWhere('a.organizationId = :organizationId')->setParameter('organizationId', $criteria->organizationId);
    }

    if ($criteria->ipHash) {
      $qb->andWhere('a.ipHash = :ipHash')->setParameter('ipHash', $criteria->ipHash);
    }

    if ($criteria->from) {
      $qb->andWhere('a.occurredAt >= :from')->setParameter('from', $criteria->from);
    }

    if ($criteria->to) {
      $qb->andWhere('a.occurredAt <= :to')->setParameter('to', $criteria->to);
    }

    if ($criteria->search) {
      $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $criteria->search);
      $qb->andWhere($qb->expr()->orX(
        $qb->expr()->like('a.action', ':search'),
        $qb->expr()->like('a.actorType', ':search'),
        $qb->expr()->like('a.actorEmail', ':search'),
      ))->setParameter('search', '%' . $escaped . '%');
    }
  }

  private function resolveSortField(string $field): string
  {
    return match ($field) {
      'action' => 'action',
      'actorType' => 'actorType',
      default => 'occurredAt',
    };
  }

  /**
   * Method mapToView.
   *
   * Maps a record to a view DTO.
   *
   * @since 1.0.0
   *
   * @param AuditEventRecord $record the record to map
   *
   * @return AuditEventView the mapped view
   */
  private function mapToView(AuditEventRecord $record): AuditEventView
  {
    return new AuditEventView(
      id: $record->id->toRfc4122(),
      action: $record->action,
      actorType: $record->actorType,
      actorId: $record->actorId,
      actorEmail: $record->actorEmail,
      actorEmailHash: $record->actorEmailHash,
      subjectType: $record->subjectType,
      subjectId: $record->subjectId,
      clientId: $record->clientId,
      tenantId: $record->tenantId,
      ipAddress: $record->ipAddress,
      ipHash: $record->ipHash,
      userAgent: $record->userAgent,
      metadata: $record->metadata,
      occurredAt: $record->occurredAt->format(DateTimeInterface::ATOM),
      recordedAt: $record->recordedAt->format(DateTimeInterface::ATOM),
      chainId: $record->chainId,
      sequence: $record->sequence,
      prevHash: $record->prevHash,
      eventHash: $record->eventHash,
      organizationId: $record->organizationId,
    );
  }
  // #endregion
}
