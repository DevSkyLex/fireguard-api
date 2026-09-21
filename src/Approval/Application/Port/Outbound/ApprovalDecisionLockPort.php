<?php

declare(strict_types=1);

namespace Approval\Application\Port\Outbound;

/**
 * Port ApprovalDecisionLockPort.
 *
 * Serializes every terminal transition with its business effects in main.
 * The callback must reread the request. Technical exceptions roll back;
 * a committed business refusal must be returned and thrown by the caller.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ApprovalDecisionLockPort
{
  /**
   * Method synchronized.
   *
   * @since 1.0.0
   *
   * @template T
   *
   * @param string $requestId the request to serialize
   * @param callable():T $decision the decision and its local consequences
   *
   * @return T the committed result
   */
  public function synchronized(string $requestId, callable $decision): mixed;
}
