<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Reminder;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, QueryBuilder};
use Intervention\Application\Contract\Reminder\{InterventionReminderCandidate, InterventionReminderPage};
use Intervention\Application\Port\Outbound\InterventionReminderPort;
use Intervention\Domain\Exception\InterventionNotFoundException;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function array_map;
use function max;

/**
 * Adapter DoctrineInterventionReminderAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineInterventionReminderAdapter implements InterventionReminderPort
{
  // #region Constants
  /**
   * Statuses where field work is still expected — the only ones eligible for
   * a due-date reminder.
   *
   * @var list<string>
   */
  private const array ACTIVE_STATUSES = ['planned', 'in_progress', 'changes_requested'];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  public function pageDueSoon(DateTimeImmutable $now, DateTimeImmutable $threshold, int $limit, int $offset): InterventionReminderPage
  {
    $qb = $this->baseQuery($limit, $offset)
      ->andWhere('m.dueSoonNotifiedAt IS NULL')
      ->andWhere('m.dueAt >= :now')
      ->andWhere('m.dueAt <= :threshold')
      ->setParameter('now', $now)
      ->setParameter('threshold', $threshold);

    return $this->page($qb);
  }

  public function pageOverdue(DateTimeImmutable $now, int $limit, int $offset): InterventionReminderPage
  {
    $qb = $this->baseQuery($limit, $offset)
      ->andWhere('m.overdueNotifiedAt IS NULL')
      ->andWhere('m.dueAt < :now')
      ->setParameter('now', $now);

    return $this->page($qb);
  }

  public function markDueSoonNotified(string $interventionId, DateTimeImmutable $at): void
  {
    $record = $this->intervention($interventionId);
    $record->dueSoonNotifiedAt = $at;
    $this->entityManager->flush();
  }

  public function markOverdueNotified(string $interventionId, DateTimeImmutable $at): void
  {
    $record = $this->intervention($interventionId);
    $record->overdueNotifiedAt = $at;
    $this->entityManager->flush();
  }

  /**
   * Method baseQuery.
   *
   * @since 1.0.0
   *
   * @param int $limit the maximum number of results
   * @param int $offset the result offset
   *
   * @return QueryBuilder the shared candidate query, before its window filter
   */
  private function baseQuery(int $limit, int $offset): QueryBuilder
  {
    return $this->entityManager->createQueryBuilder()
      ->select('m')
      ->from(InterventionRecord::class, 'm')
      ->where('m.status IN (:statuses)')
      ->andWhere('m.dueAt IS NOT NULL')
      ->setParameter('statuses', self::ACTIVE_STATUSES)
      ->orderBy('m.id', 'ASC')
      ->setFirstResult(max(0, $offset))
      ->setMaxResults(max(1, $limit));
  }

  /**
   * Method page.
   *
   * @since 1.0.0
   *
   * @param QueryBuilder $qb the query builder value
   *
   * @return InterventionReminderPage the candidate page result
   */
  private function page(QueryBuilder $qb): InterventionReminderPage
  {
    /** @var list<InterventionRecord> $records */
    $records = $qb->getQuery()->getResult();

    return new InterventionReminderPage(array_map($this->candidate(...), $records));
  }

  /**
   * Method candidate.
   *
   * @since 1.0.0
   *
   * @param InterventionRecord $record the record value
   *
   * @return InterventionReminderCandidate the candidate result
   */
  private function candidate(InterventionRecord $record): InterventionReminderCandidate
  {
    if (null === $record->dueAt) {
      throw new InterventionNotFoundException('Intervention reminder candidate is missing a due date.');
    }

    return new InterventionReminderCandidate(
      $record->id,
      $this->organizationId($record),
      $record->number,
      $record->name,
      $record->dueAt,
      $record->responsibleId,
      $record->participants,
    );
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   *
   * @param InterventionRecord $record the record value
   *
   * @return string the organization id result
   */
  private function organizationId(InterventionRecord $record): string
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new InterventionNotFoundException('Intervention reminder candidate organization is missing.');
    }

    return $record->organization->id;
  }

  /**
   * Method intervention.
   *
   * @since 1.0.0
   *
   * @param string $id the intervention id value
   *
   * @return InterventionRecord the intervention record result
   */
  private function intervention(string $id): InterventionRecord
  {
    $record = $this->entityManager->find(InterventionRecord::class, $id);
    if (!$record instanceof InterventionRecord) {
      throw InterventionNotFoundException::withId($id);
    }

    return $record;
  }
  // #endregion
}
