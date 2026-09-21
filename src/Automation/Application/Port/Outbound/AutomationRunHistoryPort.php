<?php

declare(strict_types=1);

namespace Automation\Application\Port\Outbound;

use Automation\Application\Contract\Run\{AutomationAttemptView, AutomationRetry};

/** Port AutomationRunHistoryPort. Scoped history and transactional retry reservation. */
interface AutomationRunHistoryPort
{
  /**
   * @return list<AutomationAttemptView>
   */
  public function listAttempts(string $organizationId, int $limit, int $offset, bool $canRetry): array;

  public function getAttempt(string $organizationId, string $attemptId, bool $canRetry): AutomationAttemptView;

  public function countAttempts(string $organizationId): int;

  /**
   * Caller owns the main transaction; this reserves the action row lock through commit.
   */
  public function reserveRetry(string $organizationId, string $runId, string $attemptId, string $actorUserId): AutomationRetry;
}
