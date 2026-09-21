<?php

declare(strict_types=1);

namespace Tests\Support\Approval;

use Approval\Application\Port\Outbound\ApprovalDecisionLockPort;

/**
 * Test double ImmediateApprovalDecisionLock.
 *
 * Executes unit-test callbacks; PostgreSQL integration tests cover locking.
 *
 * @category Test Support
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ImmediateApprovalDecisionLock implements ApprovalDecisionLockPort
{
  public bool $committed = false;

  public function synchronized(string $requestId, callable $decision): mixed
  {
    $result = $decision();
    $this->committed = true;

    return $result;
  }
}
