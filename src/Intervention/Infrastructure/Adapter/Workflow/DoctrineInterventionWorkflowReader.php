<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Workflow;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\{EntityManagerInterface, QueryBuilder};
use Exception;
use Intervention\Application\Contract\Export\InterventionExportCandidate;
use Intervention\Application\Contract\Workflow\{
  InterventionWorkflowContext,
  InterventionWorkflowPage,
  InterventionWorkflowView
};
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Infrastructure\Persistence\Doctrine\Mapper\InterventionViewMapper;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{
  InterventionChangeRecord,
  InterventionRecord,
  InterventionWorkItemRecord
};
use InvalidArgumentException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;

use function array_filter;
use function array_map;
use function array_values;
use function is_array;
use function is_int;
use function is_string;
use function max;
use function min;
use function sprintf;
use function strtoupper;
use function trim;

/**
 * Builds organization-scoped workflow reads and list queries.
 */
final readonly class DoctrineInterventionWorkflowReader
{
  public function __construct(
    private EntityManagerInterface $entityManager,
    private InterventionViewMapper $views,
    private InterventionResourceGatewayPort $resources,
    private InterventionWorkflowMutationSupport $support,
  ) {
  }

  /**
   * Method interventionContext.
   *
   * Executes the intervention context operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return ?InterventionWorkflowContext the intervention context result
   */
  public function interventionContext(string $interventionId): ?InterventionWorkflowContext
  {
    $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId);

    return $intervention instanceof InterventionRecord ? $this->context($intervention) : null;
  }

  /**
   * Method resourceContext.
   *
   * Executes the resource context operation.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   * @param string $id the id value
   *
   * @return ?InterventionWorkflowContext the resource context result
   */
  public function resourceContext(string $resource, string $id): ?InterventionWorkflowContext
  {
    if ('intervention' === $resource) {
      return $this->interventionContext($id);
    }
    $record = match ($resource) {
      'work_item' => $this->entityManager->find(InterventionWorkItemRecord::class, $id),
      'change' => $this->entityManager->find(InterventionChangeRecord::class, $id),
      default => null,
    };
    $intervention = $record instanceof InterventionWorkItemRecord || $record instanceof InterventionChangeRecord
      ? $record->intervention
      : null;

    return $intervention instanceof InterventionRecord ? $this->context($intervention) : null;
  }

  /**
   * Method get.
   *
   * Executes the get operation.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   * @param string $id the id value
   *
   * @return ?InterventionWorkflowView the get result
   */
  public function get(string $resource, string $id): ?InterventionWorkflowView
  {
    return match ($resource) {
      'intervention' => ($record = $this->entityManager->find(InterventionRecord::class, $id)) instanceof InterventionRecord
        ? $this->views->interventionView($record)
        : null,
      'work_item' => ($record = $this->entityManager->find(InterventionWorkItemRecord::class, $id)) instanceof InterventionWorkItemRecord
        ? $this->views->workItemView($record)
        : null,
      'change' => ($record = $this->entityManager->find(InterventionChangeRecord::class, $id)) instanceof InterventionChangeRecord
        ? $this->views->changeView($record)
        : null,
      default => null,
    };
  }

  /**
   * Method list.
   *
   * Executes the list operation.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   * @param string $scopeId the scope id value
   * @param array<string, mixed> $filters the filters value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return InterventionWorkflowPage the list result
   */
  public function list(string $resource, string $scopeId, array $filters, int $page, int $itemsPerPage, Sorting $sorting = new Sorting('updatedAt', SortDirection::DESC)): InterventionWorkflowPage
  {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));
    $qb = match ($resource) {
      'intervention' => $this->interventionListQuery($scopeId, $filters),
      'work_item' => $this->workItemListQuery($scopeId, $filters),
      'change' => $this->changeListQuery($scopeId, $filters),
      default => throw new InvalidArgumentException('Unsupported intervention workflow resource.'),
    };
    $countQb = clone $qb;
    $alias = match ($resource) {
      'intervention' => 'm',
      'work_item' => 'w',
      default => 'c',
    };

    // Each list query ships a sensible default order; an explicit request
    // replaces it. Ordering was previously hard-wired, so `order[...]` reached
    // the provider and went nowhere — a paginated caller could not decide which
    // rows the first pages hold.
    if ('intervention' === $resource) {
      $qb->orderBy('m.' . $this->interventionSortField($sorting->field), strtoupper($sorting->direction->value))
        ->addOrderBy('m.id', 'ASC');
    }
    $total = (int) $countQb->resetDQLPart('orderBy')->select('COUNT(' . $alias . '.id)')->getQuery()->getSingleScalarResult();
    if ('work_item' === $resource && is_string($filters['prioritizeAssigneeId'] ?? null)) {
      // Order the whole matching collection, not just the requested page.
      // Bind the ordering-only parameter after counting to keep COUNT's bindings valid.
      $qb->addSelect('CASE WHEN w.assigneeId = :prioritizeAssigneeId THEN 0 ELSE 1 END AS HIDDEN assigneePriority')
        ->setParameter('prioritizeAssigneeId', $filters['prioritizeAssigneeId'])
        ->orderBy('assigneePriority', 'ASC')
        ->addOrderBy('w.updatedAt', 'DESC')
        ->addOrderBy('w.id', 'ASC');
    }
    /** @var list<InterventionRecord|InterventionWorkItemRecord|InterventionChangeRecord> $records */
    $records = $qb
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();
    $metrics = 'intervention' === $resource
      ? $this->resources->listMetrics(array_map(
        static fn (InterventionRecord|InterventionWorkItemRecord|InterventionChangeRecord $record): string => $record->id,
        $records,
      ))
      : [];
    $items = array_map(function (object $record) use ($metrics): InterventionWorkflowView {
      if ($record instanceof InterventionRecord) {
        return $this->views->interventionView($record, $metrics[$record->id] ?? null);
      }
      if ($record instanceof InterventionWorkItemRecord) {
        return $this->views->workItemView($record);
      }

      return $this->views->changeView($record);
    }, $records);

    return new InterventionWorkflowPage($items, $page, $itemsPerPage, $total);
  }

  /**
   * Method countInterventions.
   *
   * Executes the count interventions operation.
   *
   * @since 1.5.0
   *
   * @param string $organizationId the organization id value
   * @param array<string, mixed> $filters the filters value
   *
   * @return int the count interventions result
   */
  public function countInterventions(string $organizationId, array $filters): int
  {
    $qb = $this->interventionListQuery($organizationId, $filters);

    return (int) $qb->resetDQLPart('orderBy')->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();
  }

  /**
   * Method listInterventionExportCandidates.
   *
   * Executes the list intervention export candidates operation.
   *
   * @since 1.5.0
   *
   * @param string $organizationId the organization id value
   * @param array<string, mixed> $filters the filters value
   *
   * @return list<InterventionExportCandidate> the list intervention export candidates result
   */
  public function listInterventionExportCandidates(string $organizationId, array $filters): array
  {
    $qb = $this->interventionListQuery($organizationId, $filters)
      ->orderBy('m.updatedAt', 'DESC')
      ->addOrderBy('m.id', 'ASC');

    /** @var list<InterventionRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map(
      static fn (InterventionRecord $record): InterventionExportCandidate => new InterventionExportCandidate(
        id: $record->id,
        name: $record->name,
        type: $record->type,
        status: $record->status,
        priority: $record->priority,
        siteId: $record->siteId,
        responsibleId: $record->responsibleId,
        dueAt: $record->dueAt?->format(DateTimeInterface::ATOM),
        createdAt: $record->createdAt->format(DateTimeInterface::ATOM),
        updatedAt: $record->updatedAt->format(DateTimeInterface::ATOM),
      ),
      $records,
    );
  }

  /**
   * Method filterValues.
   *
   * @static
   *
   * Normalizes an enum/IRI list filter that accepts one or several values: a
   * plain string becomes a one-element list, a list keeps its non-empty
   * string members. The provider already validates and normalizes; this keeps
   * the gateway safe against a caller passing the legacy scalar form.
   *
   * @since 1.4.0
   *
   * @param mixed $raw the raw filter value
   *
   * @return list<string> the normalized values
   */
  private static function filterValues(mixed $raw): array
  {
    if (is_string($raw) && '' !== $raw) {
      return [$raw];
    }
    if (!is_array($raw)) {
      return [];
    }

    return array_values(array_filter(
      array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $raw),
      static fn (string $value): bool => '' !== $value,
    ));
  }

  /**
   * Method interventionSortField.
   *
   * Maps a requested sort field to a column, falling back to `updatedAt` so an
   * unknown field can never reach DQL.
   *
   * @since 1.0.0
   *
   * @param string $field the requested sort field
   *
   * @return string the record property to order by
   */
  private function interventionSortField(string $field): string
  {
    return match ($field) {
      'name', 'status', 'type', 'priority', 'plannedStartAt', 'dueAt', 'createdAt' => $field,
      default => 'updatedAt',
    };
  }

  /**
   * Method interventionListQuery.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param array<string, mixed> $filters
   *
   * @return QueryBuilder the intervention list query result
   */
  private function interventionListQuery(string $organizationId, array $filters): QueryBuilder
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);
    $qb = $this->entityManager->createQueryBuilder()
      ->select('m')
      ->from(InterventionRecord::class, 'm')
      ->where('m.organization = :organization')
      ->setParameter('organization', $organization)
      ->orderBy('m.updatedAt', 'DESC');
    $this->applyInterventionFilters($qb, $filters);
    $this->applyMembershipFilters($qb, $organizationId, $filters);
    self::applyDateRangeFilters($qb, $filters);
    // `overdueAsOf`/`overdueExcludedStatuses` are never client input: the
    // handler derives them from the `due=overdue` filter (see
    // `ListInterventionWorkflowHandler::resolveFilters()`) and this query
    // only applies them mechanically, exactly like the `dueAtAfter`/
    // `dueAtBefore` bounds above, with which they compose.
    $overdueAsOf = $filters['overdueAsOf'] ?? null;
    if ($overdueAsOf instanceof DateTimeImmutable) {
      $qb->andWhere('m.dueAt < :overdueAsOf')->setParameter('overdueAsOf', $overdueAsOf);
    }
    $overdueExcludedStatuses = $filters['overdueExcludedStatuses'] ?? null;
    if (is_array($overdueExcludedStatuses) && [] !== $overdueExcludedStatuses) {
      $qb->andWhere('m.status NOT IN (:overdueExcludedStatuses)')->setParameter('overdueExcludedStatuses', $overdueExcludedStatuses);
    }

    return $qb;
  }

  /**
   * @param array<string, mixed> $filters
   */
  private function applyInterventionFilters(QueryBuilder $qb, array $filters): void
  {
    foreach (['type', 'status', 'priority', 'responsibleId', 'siteId'] as $filter) {
      $values = self::filterValues($filters[$filter] ?? null);
      if ([] !== $values) {
        $qb->andWhere('m.' . $filter . ' IN (:' . $filter . ')')->setParameter($filter, $values);
      }
    }
    if (is_string($filters['name'] ?? null)) {
      TrigramSearchExpression::apply($qb, 'name', $filters['name'], 'm.name');
    }
    if (is_int($filters['number'] ?? null)) {
      $qb->andWhere('m.number = :number')->setParameter('number', $filters['number']);
    }
    $labelIds = self::filterValues($filters['labelId'] ?? null);
    if ([] !== $labelIds) {
      $qb->innerJoin('m.labels', 'l')
        ->andWhere('l.id IN (:labelIds)')
        ->setParameter('labelIds', $labelIds);
    }
  }

  /**
   * @param array<string, mixed> $filters
   */
  private static function applyDateRangeFilters(QueryBuilder $qb, array $filters): void
  {
    foreach (
      [
        'dueAtAfter' => ['dueAt', '>='],
        'dueAtBefore' => ['dueAt', '<='],
        'plannedStartAtAfter' => ['plannedStartAt', '>='],
        'plannedStartAtBefore' => ['plannedStartAt', '<='],
      ] as $filter => [$field, $operator]
    ) {
      if (is_string($filters[$filter] ?? null) && '' !== $filters[$filter]) {
        try {
          $date = new DateTimeImmutable($filters[$filter]);
        } catch (Exception $exception) {
          throw new InvalidArgumentException(sprintf('The %s filter must be a valid date-time.', $filter), previous: $exception);
        }
        $qb->andWhere(sprintf('m.%s %s :%s', $field, $operator, $filter))->setParameter($filter, $date);
      }
    }
  }

  /**
   * @param array<string, mixed> $filters
   */
  private function applyMembershipFilters(QueryBuilder $qb, string $organizationId, array $filters): void
  {
    if (is_string($filters['participantId'] ?? null) && '' !== $filters['participantId']) {
      $ids = $this->entityManager->getConnection()->fetchFirstColumn(
        'SELECT id FROM interventions WHERE organization_id = :organization AND jsonb_exists(participants::jsonb, :participant)',
        ['organization' => $organizationId, 'participant' => $filters['participantId']],
      );
      $qb->andWhere([] === $ids ? '1 = 0' : 'm.id IN (:participantIds)');
      if ([] !== $ids) {
        $qb->setParameter('participantIds', $ids);
      }
    }
    if (is_string($filters['memberId'] ?? null) && '' !== $filters['memberId']) {
      $ids = $this->entityManager->getConnection()->fetchFirstColumn(
        'SELECT id FROM interventions WHERE organization_id = :organization AND jsonb_exists(participants::jsonb, :member)',
        ['organization' => $organizationId, 'member' => $filters['memberId']],
      );
      $qb->andWhere(
        [] === $ids
          ? 'm.responsibleId = :memberId'
          : '(m.responsibleId = :memberId OR m.id IN (:memberInterventionIds))',
      )->setParameter('memberId', $filters['memberId']);
      if ([] !== $ids) {
        $qb->setParameter('memberInterventionIds', $ids);
      }
    }
  }

  /**
   * Method workItemListQuery.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param array<string, mixed> $filters
   *
   * @return QueryBuilder the work item list query result
   */
  private function workItemListQuery(string $interventionId, array $filters): QueryBuilder
  {
    $intervention = $this->entityManager->getReference(InterventionRecord::class, $interventionId);
    $qb = $this->entityManager->createQueryBuilder()
      ->select('w')
      ->from(InterventionWorkItemRecord::class, 'w')
      ->where('w.intervention = :intervention')
      ->setParameter('intervention', $intervention)
      ->orderBy('w.updatedAt', 'DESC')
      ->addOrderBy('w.id', 'ASC');
    foreach (['source', 'action', 'assigneeId'] as $filter) {
      if (is_string($filters[$filter] ?? null) && '' !== $filters[$filter]) {
        $qb->andWhere('w.' . $filter . ' = :' . $filter)->setParameter($filter, $filters[$filter]);
      }
    }
    $statuses = self::filterValues($filters['status'] ?? null);
    if ([] !== $statuses) {
      $qb->andWhere('w.status IN (:workItemStatuses)')->setParameter('workItemStatuses', $statuses);
    }
    TrigramSearchExpression::apply(
      $qb,
      'workItemSearch',
      is_string($filters['search'] ?? null) ? $filters['search'] : null,
      'w.target',
      'w.action',
      'w.source',
      'w.status',
      'w.resultResource',
    );

    return $qb;
  }

  /**
   * Method changeListQuery.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param array<string, mixed> $filters
   *
   * @return QueryBuilder the change list query result
   */
  private function changeListQuery(string $interventionId, array $filters): QueryBuilder
  {
    $intervention = $this->entityManager->getReference(InterventionRecord::class, $interventionId);
    $qb = $this->entityManager->createQueryBuilder()
      ->select('c')
      ->from(InterventionChangeRecord::class, 'c')
      ->where('c.intervention = :intervention')
      ->setParameter('intervention', $intervention)
      ->orderBy('c.updatedAt', 'DESC');
    foreach (['resource', 'status'] as $filter) {
      if (is_string($filters[$filter] ?? null) && '' !== $filters[$filter]) {
        $qb->andWhere('c.' . $filter . ' = :' . $filter)->setParameter($filter, $filters[$filter]);
      }
    }
    if (is_string($filters['search'] ?? null) && '' !== trim($filters['search'])) {
      $matchingIds = $this->entityManager->getConnection()->fetchFirstColumn(
        "SELECT id FROM intervention_changes WHERE intervention_id = :intervention AND (LOWER(resource) LIKE :search ESCAPE '\\' OR LOWER(status) LIKE :search ESCAPE '\\' OR LOWER(CAST(patch AS text)) LIKE :search ESCAPE '\\')",
        [
          'intervention' => $interventionId,
          'search' => TrigramSearchExpression::likeValue(trim($filters['search'])),
        ],
      );
      $qb->andWhere([] === $matchingIds ? '1 = 0' : 'c.id IN (:changeSearchIds)');
      if ([] !== $matchingIds) {
        $qb->setParameter('changeSearchIds', $matchingIds);
      }
    }

    return $qb;
  }

  /**
   * Method context.
   *
   * Executes the context operation.
   *
   * @since 1.0.0
   *
   * @param InterventionRecord $intervention the intervention value
   *
   * @return InterventionWorkflowContext the context result
   */
  private function context(InterventionRecord $intervention): InterventionWorkflowContext
  {
    return new InterventionWorkflowContext(
      $intervention->id,
      $this->support->organizationId($intervention),
      $intervention->status,
      $intervention->responsibleId,
      $intervention->participants,
    );
  }
}
