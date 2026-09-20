<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Workload;

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
    /** @var list<InterventionWorkItemRecord> $records */
    $records = $this->entityManager->createQueryBuilder()->select('w', 'i')
      ->from(InterventionWorkItemRecord::class, 'w')->join('w.intervention', 'i')
      ->where('IDENTITY(i.organization) = :organization')->setParameter('organization', $organizationId)
      ->andWhere('i.status IN (:statuses)')->setParameter('statuses', ['draft', 'planned', 'in_progress', 'changes_requested'])
      ->andWhere('w.status NOT IN (:finished)')->setParameter('finished', ['completed', 'skipped'])
      ->orderBy('w.id', 'ASC')->getQuery()->getResult();
    $zone = new DateTimeZone($timezone);
    $result = [];
    foreach ($records as $record) {
      $parent = $record->intervention;
      if (null === $parent) {
        continue;
      }
      $result[] = new InterventionWorkContribution(
        $record->id,
        $parent->id,
        $parent->name,
        $record->assigneeId,
        $record->remainingMinutes,
        $record->workStartsOn ?? $parent->plannedStartAt?->setTimezone($zone)->format('Y-m-d'),
        $record->workEndsOn ?? $parent->dueAt?->setTimezone($zone)->format('Y-m-d'),
        'draft' === $parent->status ? 'draft' : 'committed',
        $record->revision,
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
    /** @var list<InterventionTimeEntryRecord> $records */
    $records = $this->entityManager->createQueryBuilder()->select('t', 'w', 'i')
      ->from(InterventionTimeEntryRecord::class, 't')->join('t.workItem', 'w')->join('w.intervention', 'i')
      ->where('t.organizationId = :organization')->setParameter('organization', $organizationId)
      ->andWhere('t.cancelled = false')->andWhere('t.workedOn BETWEEN :from AND :to')
      ->setParameter('from', $from)->setParameter('to', $to)->orderBy('t.id', 'ASC')->getQuery()->getResult();

    return array_map(static fn (InterventionTimeEntryRecord $record): InterventionTimeContribution => new InterventionTimeContribution(
      $record->id,
      $record->workItem->id ?? '',
      $record->memberId,
      $record->workedOn,
      $record->minutes,
      $record->revision,
      $record->workItem?->intervention?->id,
      $record->workItem?->intervention?->name,
    ), $records);
  }
}
