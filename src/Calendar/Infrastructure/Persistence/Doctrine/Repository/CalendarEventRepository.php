<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Persistence\Doctrine\Repository;

use Calendar\Application\Port\Outbound\Event\CalendarEventRepositoryPort;
use Calendar\Domain\Model\Event\CalendarEvent;
use Calendar\Domain\ValueObject\CalendarEventId;
use Calendar\Infrastructure\Persistence\Doctrine\Mapper\CalendarEventMapper;
use Calendar\Infrastructure\Persistence\Doctrine\Record\CalendarEventRecord;
use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};

use function array_map;
use function max;

/**
 * Class CalendarEventRepository
 *
 * Persists and queries standalone events without an ORM association to organization records.
 *
 * `organizationId` is queried as a plain column (not a Doctrine association):
 * `CalendarEventRecord` never carries an ORM relation to `organizations` (see
 * `Calendar\MODULE.md`).
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CalendarEventRepository implements CalendarEventRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<CalendarEventRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Creates the repository and prepares the standalone event record repository.
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
    $this->repository = $this->entityManager->getRepository(CalendarEventRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save
   *
   * Inserts or updates a calendar event, assigning its mapped fields before a new record is persisted.
   *
   * @access public
   *
   * @param CalendarEvent $event event aggregate to persist
   *
   * @return void
   */
  public function save(CalendarEvent $event): void
  {
    $record = $this->repository->find((string) $event->id());
    $isNew = !$record instanceof CalendarEventRecord;

    if ($isNew) {
      $record = new CalendarEventRecord();
    }

    // The id (an assigned, non-generated identifier) must be populated
    // BEFORE `persist()` is called: Doctrine registers a NONE-strategy
    // entity into the identity map immediately on persist(), not deferred
    // to flush() — mirrors `AssistantThreadRepository::save()`.
    CalendarEventMapper::toRecord($event, $record);

    if ($isNew) {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method remove
   *
   * Removes the persisted event record when the aggregate identifier exists.
   *
   * @access public
   *
   * @param CalendarEvent $event event aggregate identifying the record
   *
   * @return void
   */
  public function remove(CalendarEvent $event): void
  {
    $record = $this->repository->find((string) $event->id());

    if ($record instanceof CalendarEventRecord) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }

  /**
   * Method findById
   *
   * Finds a standalone event by identifier and maps it to the domain model.
   *
   * @access public
   *
   * @param CalendarEventId $id event identifier
   *
   * @return CalendarEvent|null event aggregate, or null when absent
   */
  public function findById(CalendarEventId $id): ?CalendarEvent
  {
    $record = $this->repository->find((string) $id);

    return $record instanceof CalendarEventRecord ? CalendarEventMapper::toDomain($record) : null;
  }

  /**
   * Method listBetween
   *
   * Lists organization events overlapping the requested range in start-time order.
   *
   * @access public
   *
   * @param string $organizationId organization identifier stored on the event record
   * @param DateTimeImmutable $from inclusive range start
   * @param DateTimeImmutable $to inclusive range end
   * @param int $limit maximum number of records to return, with a floor of one
   *
   * @return list<CalendarEvent> matching event aggregates
   */
  public function listBetween(string $organizationId, DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array
  {
    /** @var list<CalendarEventRecord> $records */
    $records = $this->repository->createQueryBuilder('e')
      ->where('e.organizationId = :organizationId')
      ->andWhere('e.startsAt <= :to')
      ->andWhere('COALESCE(e.endsAt, e.startsAt) >= :from')
      ->setParameter('organizationId', $organizationId)
      ->setParameter('from', $from)
      ->setParameter('to', $to)
      ->orderBy('e.startsAt', 'ASC')
      ->addOrderBy('e.id', 'ASC')
      ->setMaxResults(max(1, $limit))
      ->getQuery()
      ->getResult();

    return array_map(CalendarEventMapper::toDomain(...), $records);
  }
  // #endregion
}
