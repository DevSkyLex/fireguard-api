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
 * Repository CalendarEventRepository.
 *
 * `organizationId` is queried as a plain column (not a Doctrine association):
 * `CalendarEventRecord` never carries an ORM relation to `organizations` (see
 * `Calendar\MODULE.md`).
 *
 * @category Repository
 *
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
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(CalendarEventRecord::class);
  }
  // #endregion

  // #region Methods
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

  public function remove(CalendarEvent $event): void
  {
    $record = $this->repository->find((string) $event->id());

    if ($record instanceof CalendarEventRecord) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }

  public function findById(CalendarEventId $id): ?CalendarEvent
  {
    $record = $this->repository->find((string) $id);

    return $record instanceof CalendarEventRecord ? CalendarEventMapper::toDomain($record) : null;
  }

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
