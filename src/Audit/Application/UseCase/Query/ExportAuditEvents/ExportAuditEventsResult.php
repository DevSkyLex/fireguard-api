<?php

declare(strict_types=1);

namespace Audit\Application\UseCase\Query\ExportAuditEvents;

use Audit\Application\Contract\AuditEventView;
use Shared\Application\Message\ResultMessage;

/**
 * Result ExportAuditEventsResult.
 *
 * Carries a lazy, non-materialized stream of matching events: `rows` is a
 * generator (or other one-pass iterable) produced by the repository's
 * `toIterable()`-backed `stream()`, never a fully hydrated array. `total`
 * is known up-front (a cheap COUNT the handler already ran to enforce the
 * export row cap), so callers do not need to consume `rows` to know how
 * many events matched.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportAuditEventsResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ExportAuditEventsResult class.
   *
   * @since 1.0.0
   *
   * @param iterable<AuditEventView> $rows the matching audit events, streamed
   * @param int $total the total number of matching audit events
   */
  public function __construct(
    public iterable $rows,
    public int $total,
  ) {
  }
}
