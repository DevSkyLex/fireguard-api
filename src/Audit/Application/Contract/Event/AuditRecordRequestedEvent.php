<?php

declare(strict_types=1);

namespace Audit\Application\Contract\Event;

use Audit\Application\Contract\AuditRecordPayload;

/**
 * Class AuditRecordRequestedEvent
 *
 * Requests one immutable ledger append after the producer's outbox commits. The source identity survives fan-out retries.
 *
 * @category Event
 */
final readonly class AuditRecordRequestedEvent
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Associates a sanitized fact with the stable request or original durable delivery identity.
   *
   * @access public
   *
   * @param string $requestId stable identity used for the auth-owned consumer receipt
   * @param AuditRecordPayload $record sanitized ledger fields
   *
   * @return void
   */
  public function __construct(public string $requestId, public AuditRecordPayload $record)
  {
  }
  // #endregion
}
