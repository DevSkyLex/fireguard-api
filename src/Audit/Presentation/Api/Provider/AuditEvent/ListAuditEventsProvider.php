<?php

declare(strict_types=1);

namespace Audit\Presentation\Api\Provider\AuditEvent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Audit\Application\Contract\{AuditEventSearchCriteria, AuditEventView};
use Audit\Application\UseCase\Query\ListAuditEvents\ListAuditEventsQuery;
use Audit\Presentation\Api\Dto\Output\AuditEvent\AuditEventOutput;
use DateTimeImmutable;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Contract\Sorting\SortDirection;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};

use function array_map;
use function is_numeric;
use function is_string;
use function max;

/**
 * Provider ListAuditEventsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<AuditEventOutput>
 */
final readonly class ListAuditEventsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListAuditEventsProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  /**
   * Method provide.
   *
   * Provides a paginated list of audit events.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation metadata
   * @param array<string, mixed> $uriVariables URI variables
   * @param array<string, mixed> $context provider context (filters)
   *
   * @return TraversablePaginator<AuditEventOutput> the paginated outputs
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $filters = $context['filters'] ?? [];
    /** @var array<string, mixed> $filters */
    $pageValue = $filters['page'] ?? 1;
    $itemsPerPageValue = $filters['itemsPerPage'] ?? 30;

    $page = is_numeric($pageValue) ? (int) $pageValue : 1;
    $itemsPerPage = is_numeric($itemsPerPageValue) ? (int) $itemsPerPageValue : 30;

    $page = max(1, $page);
    $itemsPerPage = max(1, $itemsPerPage);

    $offset = ($page - 1) * $itemsPerPage;

    $criteria = new AuditEventSearchCriteria(
      action: $this->stringOrNull($filters['action'] ?? null),
      actorType: $this->stringOrNull($filters['actorType'] ?? null),
      actorId: $this->stringOrNull($filters['actorId'] ?? null),
      subjectType: $this->stringOrNull($filters['subjectType'] ?? null),
      subjectId: $this->stringOrNull($filters['subjectId'] ?? null),
      clientId: $this->stringOrNull($filters['clientId'] ?? null),
      tenantId: $this->stringOrNull($filters['tenantId'] ?? null),
      ipHash: $this->stringOrNull($filters['ipHash'] ?? null),
      actorEmailHash: $this->stringOrNull($filters['actorEmailHash'] ?? null),
      from: $this->parseDate($filters['from'] ?? null),
      to: $this->parseDate($filters['to'] ?? null),
    );

    $query = new ListAuditEventsQuery(
      criteria: $criteria,
      pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
    );

    /** @var PaginatedResult<AuditEventView> $result */
    $result = $this->queryBus->ask(query: $query);

    $outputs = array_map(
      fn (AuditEventView $view): AuditEventOutput => $this->mapToOutput($view),
      $result->items,
    );

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['action', 'actorType', 'actorEmail']);

    $sorting = SortingExtractor::fromContext($context, ['action', 'actorType', 'occurredAt'], 'occurredAt', SortDirection::DESC);
    $outputs = CollectionSorter::sort($outputs, $sorting);

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $page,
      itemsPerPage: (float) $itemsPerPage,
      totalItems: (float) $result->total,
    );
  }

  /**
   * Method mapToOutput.
   *
   * Maps a view model to an API output.
   *
   * @since 1.0.0
   *
   * @param AuditEventView $view the view to map
   *
   * @return AuditEventOutput the mapped output
   */
  private function mapToOutput(AuditEventView $view): AuditEventOutput
  {
    $output = new AuditEventOutput();
    $output->id = $view->id;
    $output->action = $view->action;
    $output->actorType = $view->actorType;
    $output->actorId = $view->actorId;
    $output->actorEmail = $view->actorEmail;
    $output->actorEmailHash = $view->actorEmailHash;
    $output->subjectType = $view->subjectType;
    $output->subjectId = $view->subjectId;
    $output->clientId = $view->clientId;
    $output->tenantId = $view->tenantId;
    $output->ipAddress = $view->ipAddress;
    $output->ipHash = $view->ipHash;
    $output->userAgent = $view->userAgent;
    $output->metadata = $view->metadata;
    $output->occurredAt = $view->occurredAt;
    $output->recordedAt = $view->recordedAt;
    $output->chainId = $view->chainId;
    $output->sequence = $view->sequence;
    $output->prevHash = $view->prevHash;
    $output->eventHash = $view->eventHash;

    return $output;
  }

  /**
   * Method parseDate.
   *
   * Parses a date string (ISO 8601 or parseable format).
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw input value
   *
   * @return DateTimeImmutable|null the parsed date or null
   */
  private function parseDate(mixed $value): ?DateTimeImmutable
  {
    if (!is_string($value) || '' === $value) {
      return null;
    }

    $parsed = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $value);
    if ($parsed instanceof DateTimeImmutable) {
      return $parsed;
    }

    $parsed = new DateTimeImmutable($value);

    return $parsed;
  }

  /**
   * Method stringOrNull.
   *
   * Returns the string value or null when empty.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw value
   *
   * @return string|null the sanitized string or null
   */
  private function stringOrNull(mixed $value): ?string
  {
    if (!is_string($value) || '' === $value) {
      return null;
    }

    return $value;
  }
}
