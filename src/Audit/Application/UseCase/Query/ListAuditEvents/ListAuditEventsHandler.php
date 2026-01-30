<?php

declare(strict_types=1);

namespace Audit\Application\UseCase\Query\ListAuditEvents;

use Audit\Application\Contract\AuditEventView;
use Audit\Application\Port\Outbound\AuditEventRepositoryPort;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;

/**
 * Handler ListAuditEventsHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListAuditEventsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListAuditEventsHandler class.
   *
   * @since 1.0.0
   *
   * @param AuditEventRepositoryPort $repository the audit repository
   */
  public function __construct(
    private AuditEventRepositoryPort $repository,
  ) {
  }
  // #endregion

  /**
   * @since 1.0.0
   *
   * @param ListAuditEventsQuery $query the query to handle
   *
   * @return PaginatedResult<AuditEventView>
   */
  public function __invoke(ListAuditEventsQuery $query): PaginatedResult
  {
    return $this->repository->search(
      criteria: $query->criteria,
      pagination: $query->pagination,
    );
  }
}
