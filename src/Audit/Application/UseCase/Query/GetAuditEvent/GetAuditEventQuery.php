<?php

declare(strict_types=1);

namespace Audit\Application\UseCase\Query\GetAuditEvent;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetAuditEventQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetAuditEventQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetAuditEventQuery class.
   *
   * @since 1.0.0
   *
   * @param string $eventId the audit event ID
   */
  public function __construct(
    public string $eventId,
  ) {
  }
}
