<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Workload;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Workload\{InterventionTimeContribution, InterventionWorkContribution};
use Intervention\Application\Port\Inbound\InterventionWorkloadContributionsPort;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionTimeEntryRecord, InterventionWorkItemRecord};

use function array_map;

/**
 * Adapter InterventionWorkloadContributionsAdapter. Unpaginated, organization-scoped projection.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionWorkloadContributionsAdapter implements InterventionWorkloadContributionsPort
{
  /**
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager explicitly configured main entity manager
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  /**
   * Reads all task contributions for the organization, independently of UI pagination.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param string $timezone organization IANA timezone used to interpret local dates
   *
   * @return list<InterventionWorkContribution>
   */
  public function tasks(string $organizationId, string $timezone): array
  {
    /** @var list<array{taskId: string, interventionId: string, label: string, memberId: ?string, remainingMinutes: ?int, startsOn: ?string, endsOn: ?string, plannedStartAt: ?DateTimeImmutable, dueAt: ?DateTimeImmutable, status: string, revision: int}> $records */
    $records = $this->entityManager->createQueryBuilder()->select('w.id AS taskId', 'i.id AS interventionId', 'i.name AS label', 'w.assigneeId AS memberId', 'w.remainingMinutes AS remainingMinutes', 'w.workStartsOn AS startsOn', 'w.workEndsOn AS endsOn', 'i.plannedStartAt AS plannedStartAt', 'i.dueAt AS dueAt', 'i.status AS status', 'w.revision AS revision')
      ->from(InterventionWorkItemRecord::class, 'w')->join('w.intervention', 'i')
      ->where('IDENTITY(i.organization) = :organization')->setParameter('organization', $organizationId)
      ->andWhere('i.status IN (:statuses)')->setParameter('statuses', ['draft', 'planned', 'in_progress', 'changes_requested'])
      ->andWhere('w.status NOT IN (:finished)')->setParameter('finished', ['completed', 'skipped'])
      ->orderBy('w.id', 'ASC')->getQuery()->getArrayResult();
    $zone = new DateTimeZone($timezone);
    $result = [];
    foreach ($records as $record) {
      $result[] = new InterventionWorkContribution(
        $record['taskId'],
        $record['interventionId'],
        $record['label'],
        $record['memberId'],
        $record['remainingMinutes'],
        $record['startsOn'] ?? $record['plannedStartAt']?->setTimezone($zone)->format('Y-m-d'),
        $record['endsOn'] ?? $record['dueAt']?->setTimezone($zone)->format('Y-m-d'),
        'draft' === $record['status'] ? 'draft' : 'committed',
        $record['revision'],
      );
    }

    return $result;
  }

  /**
   * Reads uncancelled actual work for the requested local-date period.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param string $from inclusive first local date in the requested period
   * @param string $to inclusive last local date in the requested period
   *
   * @return list<InterventionTimeContribution>
   */
  public function actuals(string $organizationId, string $from, string $to): array
  {
    /** @var list<array{id: string, taskId: string, memberId: string, workedOn: string, minutes: int, revision: int, interventionId: string, label: string}> $records */
    $records = $this->entityManager->createQueryBuilder()->select('t.id AS id', 'w.id AS taskId', 't.memberId AS memberId', 't.workedOn AS workedOn', 't.minutes AS minutes', 't.revision AS revision', 'i.id AS interventionId', 'i.name AS label')
      ->from(InterventionTimeEntryRecord::class, 't')->join('t.workItem', 'w')->join('w.intervention', 'i')
      ->where('t.organizationId = :organization')->setParameter('organization', $organizationId)
      ->andWhere('t.cancelled = false')->andWhere('t.workedOn BETWEEN :from AND :to')
      ->setParameter('from', $from)->setParameter('to', $to)->orderBy('t.id', 'ASC')->getQuery()->getArrayResult();

    return array_map(static fn (array $record): InterventionTimeContribution => new InterventionTimeContribution(
      $record['id'],
      $record['taskId'],
      $record['memberId'],
      $record['workedOn'],
      $record['minutes'],
      $record['revision'],
      $record['interventionId'],
      $record['label'],
    ), $records);
  }
}
