<?php

declare(strict_types=1);

namespace Audit\Application\UseCase\Query\ExportAuditEvents;

use Audit\Application\Contract\AuditEventSearchCriteria;
use Shared\Application\Message\QueryMessage;

/**
 * Query ExportAuditEventsQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportAuditEventsQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ExportAuditEventsQuery class.
   *
   * @since 1.0.0
   *
   * @param AuditEventSearchCriteria $criteria the search criteria (same filters as the list query)
   */
  public function __construct(
    public AuditEventSearchCriteria $criteria,
  ) {
  }
}
