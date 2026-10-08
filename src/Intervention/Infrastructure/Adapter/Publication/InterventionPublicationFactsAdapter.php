<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Publication;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Publication\{InterventionEconomicContext, InterventionEconomicContextPage, InterventionEconomicSourceFilter, InterventionFactsScopeTooLarge, InterventionPublicationFacts, InterventionPublicationFactsPage, InterventionPublishedWorkFact};
use Intervention\Application\Port\Inbound\InterventionPublicationFactsPort;
use Intervention\Application\Port\Outbound\{InterventionEconomicScopePort, InterventionEquipmentSnapshotPort};
use Intervention\Infrastructure\Persistence\Doctrine\Mapper\InterventionPublicationFactsMapper;
use InvalidArgumentException;
use ServiceRequest\Application\Port\Outbound\ServiceRequestSiteTargetPort;
use UnexpectedValueException;

use function array_map;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_int;
use function is_string;
use function json_decode;
use function mb_strlen;
use function str_replace;
use function strcmp;
use function usort;

/**
 * Class InterventionPublicationFactsAdapter
 *
 * Owns organization-scoped and bounded operational source reads for authorized finance and export consumers.
 *
 * @category Adapter
 */
final readonly class InterventionPublicationFactsAdapter implements InterventionPublicationFactsPort
{
  // #region Properties
  /**
   * Constant SOURCE
   *
   * Selects one completed publication without multiplying intervention rows.
   */
  private const string SOURCE = "FROM interventions i LEFT JOIN LATERAL (
    SELECT id, completed_at FROM intervention_publications
    WHERE intervention_id = i.id AND status = 'completed'
    ORDER BY completed_at DESC NULLS LAST, id ASC LIMIT 1
    ) p ON TRUE";

  /**
   * Constant SOURCE_DATE
   *
   * Publication evidence, rather than mutable update time, anchors historical financial windows.
   */
  private const string SOURCE_DATE = "CASE WHEN i.status = 'published' THEN COALESCE((i.closure_snapshot::jsonb ->> 'publishedAt')::timestamptz AT TIME ZONE 'UTC', p.completed_at, (i.closure_snapshot::jsonb ->> 'capturedAt')::timestamptz AT TIME ZONE 'UTC') ELSE COALESCE(i.planned_start_at, i.created_at) END";

  /**
   * Constant TARGET_ID
   *
   * Reads canonical and historical JSON equipment targets without casting arbitrary target strings as JSON.
   */
  private const string TARGET_ID = "COALESCE(substring(w.target from '^/api/equipment/([^/]+)$'), substring(w.target from '\"equipmentId\"[[:space:]]*:[[:space:]]*\"([^\"]+)\"'))";

  /**
   * Constant SQL_AND
   */
  private const string SQL_AND = ' AND ';

  /**
   * Constant SQL_WHERE
   */
  private const string SQL_WHERE = ' WHERE ';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Every owned persistence read names the main manager; sibling identities are resolved through public ports.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager explicit main manager
   * @param InterventionPublicationFactsMapper $mapper immutable source mapper
   * @param InterventionEquipmentSnapshotPort $equipment owning asset identity bridge
   * @param InterventionEconomicScopePort $scopes owning location scope bridge
   * @param ServiceRequestSiteTargetPort $sites published root site identity bridge
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager, private InterventionPublicationFactsMapper $mapper, private InterventionEquipmentSnapshotPort $equipment, private InterventionEconomicScopePort $scopes, private ServiceRequestSiteTargetPort $sites)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method published
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param string $interventionId requested work
   *
   * @return ?InterventionPublicationFacts original dossier or explicit missing-snapshot facts
   */
  public function published(string $organizationId, string $interventionId): ?InterventionPublicationFacts
  {
    return $this->publishedBatch($organizationId, [$interventionId])[0] ?? null;
  }

  /**
   * Method publishedBatch
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param list<string> $interventionIds bounded unique source identities
   *
   * @return list<InterventionPublicationFacts> organization-scoped published facts
   */
  public function publishedBatch(string $organizationId, array $interventionIds): array
  {
    $interventionIds = array_values(array_unique($interventionIds));
    if (count($interventionIds) > 100) {
      throw new InterventionFactsScopeTooLarge('A publication batch may contain at most 100 interventions.');
    }
    if ([] === $interventionIds) {
      return [];
    }
    $rows = $this->rows("i.organization_id = :organization AND i.status = 'published' AND i.id IN (:ids)", ['organization' => $organizationId, 'ids' => $interventionIds], ['ids' => ArrayParameterType::STRING], count($interventionIds), 0, 10000);

    $facts = array_map(fn (array $row): InterventionPublicationFacts => $this->publication($row), $rows);
    usort($facts, static fn (InterventionPublicationFacts $left, InterventionPublicationFacts $right): int => strcmp($left->id, $right->id));

    return $facts;
  }

  /**
   * Method publishedPage
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param int $page one-based page
   * @param int $itemsPerPage bounded size
   * @param ?string $search literal retained title or organization sequence
   *
   * @return InterventionPublicationFactsPage exact published directory
   */
  public function publishedPage(string $organizationId, int $page = 1, int $itemsPerPage = 50, ?string $search = null): InterventionPublicationFactsPage
  {
    $this->pagination($page, $itemsPerPage, 100);
    $where = "i.organization_id = :organization AND i.status = 'published'";
    $parameters = ['organization' => $organizationId];
    if (null !== $search && '' !== $search) {
      if (mb_strlen($search, 'UTF-8') > 160) {
        throw new InvalidArgumentException('The source search may contain at most 160 characters.');
      }
      $where .= " AND (COALESCE(i.closure_snapshot::jsonb ->> 'name', i.closure_snapshot::jsonb -> 'report' ->> 'name', i.name) ILIKE :search ESCAPE '!' OR i.number::text ILIKE :search ESCAPE '!')";
      $parameters['search'] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
    }
    $rows = $this->rows($where, $parameters, [], $itemsPerPage, ($page - 1) * $itemsPerPage, 10000);

    return new InterventionPublicationFactsPage(array_map(fn (array $row): InterventionPublicationFacts => $this->publication($row), $rows), $this->total($where, $parameters, []), $page, $itemsPerPage);
  }

  /**
   * Method economicContext
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param string $interventionId requested source
   *
   * @return ?InterventionEconomicContext immutable or explicit current allocation facts
   */
  public function economicContext(string $organizationId, string $interventionId): ?InterventionEconomicContext
  {
    $rows = $this->rows('i.organization_id = :organization AND i.id = :id', ['organization' => $organizationId, 'id' => $interventionId], [], 1, 0);

    return [] === $rows ? null : $this->context($rows[0]);
  }

  /**
   * Method economicPage
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param int $page one-based page
   * @param int $itemsPerPage bounded size
   * @param ?InterventionEconomicSourceFilter $filter bounded operational predicates and authorized financial-source identities
   *
   * @return InterventionEconomicContextPage exact matching source directory
   */
  public function economicPage(string $organizationId, int $page = 1, int $itemsPerPage = 50, ?InterventionEconomicSourceFilter $filter = null): InterventionEconomicContextPage
  {
    $this->pagination($page, $itemsPerPage, 100);

    return $this->economicSources($organizationId, $page, $itemsPerPage, $filter ?? new InterventionEconomicSourceFilter());
  }

  /**
   * Method economicWindow
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param DateTimeImmutable $from inclusive source date
   * @param DateTimeImmutable $to exclusive source date
   * @param ?string $siteId optional root site
   * @param ?string $customerId optional internal client
   * @param ?string $equipmentId optional asset
   * @param int $limit bounded report source count
   *
   * @return InterventionEconomicContextPage first bounded page and exact filtered count
   */
  public function economicWindow(string $organizationId, DateTimeImmutable $from, DateTimeImmutable $to, ?string $siteId = null, ?string $customerId = null, ?string $equipmentId = null, int $limit = 501): InterventionEconomicContextPage
  {
    $this->pagination(1, $limit, 501);

    return $this->economicSources($organizationId, 1, $limit, new InterventionEconomicSourceFilter(from: $from, to: $to, siteId: $siteId, customerId: $customerId, equipmentId: $equipmentId));
  }

  /**
   * Method economicSources
   *
   * Applies the identical source predicate to count and page. Filters use immutable published identities and bounded owned live scopes.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param int $page validated page
   * @param int $size validated source limit
   * @param InterventionEconomicSourceFilter $filter bounded source predicates and authorized additional identifiers
   *
   * @return InterventionEconomicContextPage bounded exact directory
   */
  private function economicSources(string $organizationId, int $page, int $size, InterventionEconomicSourceFilter $filter): InterventionEconomicContextPage
  {
    $financialInterventionIds = array_values(array_unique($filter->financialInterventionIds));
    if (count($financialInterventionIds) > 10000) {
      throw new InterventionFactsScopeTooLarge('The financial source scope exceeds 10000 intervention identifiers.');
    }
    if (null !== $filter->from && null !== $filter->to && $filter->from >= $filter->to) {
      throw new InvalidArgumentException('The source window must have an exclusive end after its start.');
    }
    $filters = ['i.organization_id = :organization'];
    $parameters = ['organization' => $organizationId];
    $types = [];
    if (null !== $filter->from) {
      $filters[] = self::SOURCE_DATE . ' >= :from';
      $parameters['from'] = $filter->from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
    if (null !== $filter->to) {
      $filters[] = self::SOURCE_DATE . ' < :to';
      $parameters['to'] = $filter->to->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
    if (null !== $filter->search && '' !== $filter->search) {
      if (mb_strlen($filter->search, 'UTF-8') > 160) {
        throw new InvalidArgumentException('The source search may contain at most 160 characters.');
      }
      $filters[] = "(CASE WHEN i.status = 'published' THEN COALESCE(i.closure_snapshot::jsonb ->> 'name', i.closure_snapshot::jsonb -> 'report' ->> 'name', i.name) ELSE i.name END ILIKE :search ESCAPE '!' OR i.number::text ILIKE :search ESCAPE '!')";
      $parameters['search'] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filter->search) . '%';
    }
    if (null !== $filter->siteId || null !== $filter->customerId || null !== $filter->equipmentId) {
      $identityFilter = $this->identityFilter($organizationId, $filter->siteId, $filter->customerId, $filter->equipmentId, $parameters, $types);
      if ([] !== $financialInterventionIds) {
        $parameters['financialSources'] = $financialInterventionIds;
        $types['financialSources'] = ArrayParameterType::STRING;
        $identityFilter = '(' . $identityFilter . ' OR i.id IN (:financialSources))';
      }
      $filters[] = $identityFilter;
    }
    $where = implode(self::SQL_AND, $filters);
    $total = $this->total($where, $parameters, $types);
    if (0 === $total) {
      return new InterventionEconomicContextPage([], 0, $page, $size);
    }
    $rows = $this->rows($where, $parameters, $types, $size, ($page - 1) * $size);

    return new InterventionEconomicContextPage($this->contexts($rows), $total, $page, $size);
  }

  /**
   * Method contexts
   *
   * Enforces the combined task bound while mapping the same exact page selected by the count predicate.
   *
   * @access private
   *
   * @param list<array<string,mixed>> $rows bounded owned source rows
   *
   * @return list<InterventionEconomicContext> immutable or explicitly current contexts
   */
  private function contexts(array $rows): array
  {
    $items = [];
    $taskCount = 0;
    foreach ($rows as $row) {
      $context = $this->context($row);
      $taskCount += count($context->workItems);
      if ($taskCount > 20000) {
        throw new InterventionFactsScopeTooLarge('The source page exceeds 20000 tasks; narrow the requested scope.');
      }
      $items[] = $context;
    }

    return $items;
  }

  /**
   * Method identityFilter
   *
   * Applies combined site/client/equipment filters to the same captured task rather than mixing unrelated tasks.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param ?string $siteId root site filter
   * @param ?string $customerId client filter
   * @param ?string $equipmentId asset filter
   * @param array<string,mixed> $parameters accumulated parameters
   * @param array<string,int> $types accumulated array parameter types
   *
   * @return string scoped SQL predicate
   */
  private function identityFilter(string $organizationId, ?string $siteId, ?string $customerId, ?string $equipmentId, array &$parameters, array &$types): string
  {
    $published = $this->publishedIdentityFilter($siteId, $customerId, $equipmentId, $parameters);
    $live = $this->liveIdentityFilter($organizationId, $siteId, $customerId, $equipmentId, $parameters, $types);

    return "((i.status = 'published' AND (" . $published . ")) OR (i.status <> 'published' AND (" . $live . ')))';
  }

  /**
   * Method publishedIdentityFilter
   *
   * Combines retained root and same-task identity without consulting current locations or assets.
   *
   * @access private
   *
   * @param ?string $siteId retained root site filter
   * @param ?string $customerId retained internal client filter
   * @param ?string $equipmentId retained asset filter
   * @param array<string,mixed> $parameters shared bound values
   *
   * @return string immutable published source predicate
   */
  private function publishedIdentityFilter(?string $siteId, ?string $customerId, ?string $equipmentId, array &$parameters): string
  {
    $root = [];
    $task = [];
    if (null !== $siteId) {
      $parameters['site'] = $siteId;
      $root[] = "i.closure_snapshot::jsonb -> 'site' ->> 'id' = :site";
      $task[] = "COALESCE(j -> 'site' ->> 'id', j -> 'equipmentIdentity' -> 'site' ->> 'id') = :site";
    }
    if (null !== $customerId) {
      $parameters['customer'] = $customerId;
      $root[] = "i.closure_snapshot::jsonb -> 'customer' ->> 'id' = :customer";
      $task[] = "COALESCE(j -> 'customer' ->> 'id', j -> 'equipmentIdentity' -> 'customer' ->> 'id') = :customer";
    }
    if (null !== $equipmentId) {
      $parameters['equipment'] = $equipmentId;
      $task[] = "COALESCE(j -> 'equipmentIdentity' ->> 'id', substring(j ->> 'target' from '^/api/equipment/([^/]+)$'), substring(j ->> 'target' from '\"equipmentId\"[[:space:]]*:[[:space:]]*\"([^\"]+)\"')) = :equipment";
    }
    $published = "EXISTS (SELECT 1 FROM jsonb_array_elements(COALESCE(i.closure_snapshot::jsonb -> 'workItems', '[]'::jsonb)) j WHERE " . implode(self::SQL_AND, $task) . ')';
    if (null === $equipmentId && [] !== $root) {
      $published = '(' . implode(self::SQL_AND, $root) . ') OR ' . $published;
    }

    return $published;
  }

  /**
   * Method liveIdentityFilter
   *
   * Resolves bounded current organization scopes only for unpublished sources.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param ?string $siteId current root site filter
   * @param ?string $customerId current internal client filter
   * @param ?string $equipmentId current asset filter
   * @param array<string,mixed> $parameters shared bound values
   * @param array<string,int> $types shared array parameter types
   *
   * @return string current source predicate or explicit false
   */
  private function liveIdentityFilter(string $organizationId, ?string $siteId, ?string $customerId, ?string $equipmentId, array &$parameters, array &$types): string
  {
    $live = [];
    $scope = null === $siteId && null === $customerId ? null : $this->scopes->facilityIds($organizationId, $siteId, $customerId);
    $equipmentScope = null === $scope ? null : $this->equipment->equipmentIdsInFacilities($organizationId, $scope);
    if (null !== $equipmentId) {
      if (isset($this->equipment->snapshots($organizationId, [$equipmentId])[$equipmentId]) && (null === $scope || in_array($equipmentId, $equipmentScope ?? [], true))) {
        $live[] = 'EXISTS (SELECT 1 FROM intervention_work_items w WHERE w.intervention_id = i.id AND ' . self::TARGET_ID . ' = :equipment)';
      }
    } elseif (null !== $scope && [] !== $scope) {
      $parameters['facilities'] = $scope;
      $types['facilities'] = ArrayParameterType::STRING;
      $live[] = 'i.site_id IN (:facilities)';
      if ([] !== $equipmentScope) {
        $parameters['assets'] = $equipmentScope;
        $types['assets'] = ArrayParameterType::STRING;
        $live[] = 'EXISTS (SELECT 1 FROM intervention_work_items w WHERE w.intervention_id = i.id AND ' . self::TARGET_ID . ' IN (:assets))';
      }
    }

    return [] === $live ? 'FALSE' : implode(' OR ', $live);
  }

  /**
   * Method rows
   *
   * @access private
   *
   * @param string $where scoped predicate
   * @param array<string,mixed> $parameters source parameters
   * @param array<string,int> $types array parameter types
   * @param int $limit validated bound
   * @param int $offset validated pagination offset
   * @param int $maximumTasks task bound before transferring source dossiers
   *
   * @return list<array<string,mixed>> owned scalar source rows
   */
  private function rows(string $where, array $parameters, array $types, int $limit, int $offset, int $maximumTasks = 20000): array
  {
    $this->assertPageBounded($where, $parameters, $types, $limit, $offset, $maximumTasks);

    return $this->entityManager->getConnection()->fetchAllAssociative('SELECT i.*, p.id AS publication_id, p.completed_at AS published_at ' . self::SOURCE . self::SQL_WHERE . $where . ' ORDER BY i.created_at DESC, i.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset, $parameters, $types);
  }

  /**
   * Method assertPageBounded
   *
   * Counts selected source records before transferring large JSON dossiers into PHP, so limits cannot be bypassed by a page of large publications.
   *
   * @access private
   *
   * @param string $where identical scoped page predicate
   * @param array<string,mixed> $parameters source parameters
   * @param array<string,int> $types array parameter types
   * @param int $limit validated page bound
   * @param int $offset validated page offset
   * @param int $maximumTasks task bound before transferring source dossiers
   *
   * @return void
   */
  private function assertPageBounded(string $where, array $parameters, array $types, int $limit, int $offset, int $maximumTasks): void
  {
    $selected = 'SELECT i.id, i.status, i.closure_snapshot ' . self::SOURCE . self::SQL_WHERE . $where . ' ORDER BY i.created_at DESC, i.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset;
    $snapshotTasks = "CASE WHEN jsonb_typeof(s.closure_snapshot::jsonb -> 'workItems') = 'array' THEN jsonb_array_length(s.closure_snapshot::jsonb -> 'workItems') ELSE 0 END";
    $snapshotProofs = "CASE WHEN jsonb_typeof(s.closure_snapshot::jsonb -> 'attachments') = 'array' THEN jsonb_array_length(s.closure_snapshot::jsonb -> 'attachments') ELSE 0 END";
    $snapshotTimes = "CASE WHEN jsonb_typeof(s.closure_snapshot::jsonb -> 'timeEntries') = 'array' THEN jsonb_array_length(s.closure_snapshot::jsonb -> 'timeEntries') ELSE 0 END";
    $snapshotActivities = "CASE WHEN jsonb_typeof(s.closure_snapshot::jsonb -> 'report' -> 'activities') = 'array' THEN jsonb_array_length(s.closure_snapshot::jsonb -> 'report' -> 'activities') ELSE 0 END";
    /** @var array{task_count:string|int,auxiliary_count:string|int,dossier_bytes:string|int}|false $counts */
    $counts = $this->entityManager->getConnection()->fetchAssociative('WITH selected AS (' . $selected . ') SELECT COALESCE(SUM(CASE WHEN s.status = \'published\' THEN ' . $snapshotTasks . ' ELSE (SELECT COUNT(*) FROM intervention_work_items w WHERE w.intervention_id = s.id) END), 0) AS task_count, COALESCE(SUM(' . $snapshotProofs . ' + ' . $snapshotTimes . ' + ' . $snapshotActivities . '), 0) AS auxiliary_count, COALESCE(SUM(octet_length(s.closure_snapshot::text)), 0) AS dossier_bytes FROM selected s', $parameters, $types);
    if (false === $counts) {
      throw new UnexpectedValueException('The selected operational source counts are unavailable.');
    }
    if ((int) $counts['task_count'] > $maximumTasks || (int) $counts['auxiliary_count'] > 50000 || (int) $counts['dossier_bytes'] > 67108864) {
      throw new InterventionFactsScopeTooLarge('The selected source exceeds its ' . $maximumTasks . '-task, 50000 auxiliary-record or 64 MiB dossier bound; narrow the requested scope.');
    }
  }

  /**
   * Method total
   *
   * @access private
   *
   * @param string $where identical page predicate
   * @param array<string,mixed> $parameters source parameters
   * @param array<string,int> $types array parameter types
   *
   * @return int exact filtered intervention count
   */
  private function total(string $where, array $parameters, array $types): int
  {
    $total = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) ' . self::SOURCE . self::SQL_WHERE . $where, $parameters, $types);

    return is_int($total) || is_string($total) ? (int) $total : throw new UnexpectedValueException('The scoped intervention count is unavailable.');
  }

  /**
   * Method context
   *
   * @access private
   *
   * @param array<string,mixed> $row owned scoped row
   *
   * @return InterventionEconomicContext actual immutable or current source
   */
  private function context(array $row): InterventionEconomicContext
  {
    $snapshot = $this->mapper->snapshot($row['closure_snapshot']);
    if ('published' === $row['status']) {
      return $this->mapper->context($row, $snapshot);
    }
    $organizationId = is_string($row['organization_id']) ? $row['organization_id'] : throw new InvalidArgumentException('The source organization is invalid.');
    $interventionId = is_string($row['id']) ? $row['id'] : throw new InvalidArgumentException('The source identifier is invalid.');
    $siteId = is_string($row['site_id']) ? $row['site_id'] : null;
    $site = null === $siteId ? null : $this->sites->find($organizationId, null, $siteId);

    return $this->mapper->context($row, null, $this->liveItems($organizationId, $interventionId), null === $site ? null : ['id' => $site->id, 'name' => $site->name], $site?->customer);
  }

  /**
   * Method publication
   *
   * @access private
   *
   * @param array<string,mixed> $row owned scoped published row
   *
   * @return InterventionPublicationFacts exact retained dossier and typed facts
   */
  private function publication(array $row): InterventionPublicationFacts
  {
    $snapshot = $this->mapper->snapshot($row['closure_snapshot']);
    $context = $this->mapper->context($row, $snapshot);

    return new InterventionPublicationFacts($context->id, $context->organizationId, $context->number, $context->name, $context->type, $context->status, $context->revision, $context->createdAt, $context->plannedStartAt, $context->dueAt, $context->publishedAt, $context->publicationId, 'snapshot_missing' === $context->snapshotState ? 'snapshot_missing' : 'available', $context->snapshotVersion, $context->identityComplete, $context->site, $context->customer, $context->workItems, $snapshot);
  }

  /**
   * Method liveItems
   *
   * Aggregates owned time and proof counts without loading attachment blobs or joining foreign modules.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param string $interventionId scoped source identifier
   *
   * @return list<InterventionPublishedWorkFact> bounded current task facts
   */
  private function liveItems(string $organizationId, string $interventionId): array
  {
    $rows = $this->entityManager->getConnection()->fetchAllAssociative('SELECT w.*, (SELECT COALESCE(SUM(t.minutes), 0) FROM intervention_time_entries t WHERE t.work_item_id = w.id AND t.organization_id = :organization AND NOT t.cancelled) AS spent_minutes, (SELECT COUNT(*) FROM intervention_attachments a WHERE a.work_item_id = w.id AND a.intervention_id = :intervention) AS evidence_count FROM intervention_work_items w WHERE w.intervention_id = :intervention ORDER BY w.id LIMIT 10001', ['organization' => $organizationId, 'intervention' => $interventionId]);
    if (count($rows) > 10000) {
      throw new InterventionFactsScopeTooLarge('The current source exceeds 10000 tasks; narrow the source scope.');
    }
    $ids = [];
    foreach ($rows as $row) {
      $id = $this->mapper->equipmentId(is_string($row['target']) ? $row['target'] : null);
      if (null !== $id) {
        $ids[] = $id;
      }
    }
    $equipment = $this->equipment->snapshots($organizationId, array_values(array_unique($ids)));
    $result = [];
    foreach ($rows as $row) {
      $target = is_string($row['target']) ? $row['target'] : null;
      $id = $this->mapper->equipmentId($target);
      $execution = $row['execution_result'];
      if (is_string($execution)) {
        $execution = json_decode($execution, true);
      }
      $result[] = $this->mapper->workFact(['id' => $row['id'], 'action' => $row['action'], 'status' => $row['status'], 'target' => $target, 'resultResource' => $row['result_resource'], 'executionResult' => $execution, 'spentMinutes' => $row['spent_minutes'], 'evidenceCount' => $row['evidence_count'], 'createdAt' => $row['created_at'], 'updatedAt' => $row['updated_at']], null === $id ? null : ($equipment[$id] ?? null));
    }

    return $result;
  }

  /**
   * Method pagination
   *
   * @access private
   *
   * @param int $page one-based requested page
   * @param int $size requested bound
   * @param int $maximum allowed size
   *
   * @return void
   */
  private function pagination(int $page, int $size, int $maximum): void
  {
    if ($page < 1 || $page > 1000000 || $size < 1 || $size > $maximum) {
      throw new InvalidArgumentException('The source pagination is outside its bounded range.');
    }
  }
  // #endregion
}
