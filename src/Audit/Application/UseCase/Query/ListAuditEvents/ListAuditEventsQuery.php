<?php

declare(strict_types=1);

namespace Audit\Application\UseCase\Query\ListAuditEvents;

use Audit\Application\Contract\AuditEventSearchCriteria;
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\QueryMessage;

/**
 * Query ListAuditEventsQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListAuditEventsQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListAuditEventsQuery class.
   *
   * @since 1.0.0
   *
   * @param AuditEventSearchCriteria $criteria the search criteria
   * @param Pagination $pagination pagination parameters
   */
  public function __construct(
    public AuditEventSearchCriteria $criteria,
    public Pagination $pagination = new Pagination(),
  ) {
  }
}
