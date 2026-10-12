<?php

declare(strict_types=1);

namespace Audit\Application\Port\Inbound;

use Audit\Application\Contract\AuditRecordRequest;
use LogicException;

/**
 * Interface AuditRecordingPort
 *
 * Publishes a generic durable write capability. Owners decide which fact to audit; Audit owns privacy, ledger append and delivery receipts.
 *
 * @category Port
 */
interface AuditRecordingPort
{
  // #region Methods
  /**
   * Method requestRecord
   *
   * Enqueues a sanitized request on the active owning auth/main transaction. Without a producer transaction it commits an auth enqueue; ledger append always follows delivery.
   *
   * @access public
   *
   * @param AuditRecordRequest $request fact authorized and projected by its owning module
   *
   * @return void
   *
   * @throws LogicException when both database transactions are active and ownership is ambiguous
   */
  public function requestRecord(AuditRecordRequest $request): void;
  // #endregion
}
