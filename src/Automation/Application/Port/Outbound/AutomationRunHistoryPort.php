<?php

declare(strict_types=1);

namespace Automation\Application\Port\Outbound;

use Automation\Application\Contract\Run\{AutomationAttemptView, AutomationRetry};

/** Port AutomationRunHistoryPort. Scoped history and transactional retry reservation. */
interface AutomationRunHistoryPort
{
  // #region Methods
  /**
   * @return list<AutomationAttemptView>
   */
  public function listAttempts(string $organizationId, int $limit, int $offset, bool $canRetry): array;

  /**
   * Method getAttempt
   *
   * Finds one automation attempt in the selected run history.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $attemptId the attempt identifier
   * @param bool $canRetry the can retry
   *
   * @return AutomationAttemptView
   */
  public function getAttempt(string $organizationId, string $attemptId, bool $canRetry): AutomationAttemptView;

  /**
   * Method countAttempts
   *
   * Counts automation attempts recorded for the selected run.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   *
   * @return int
   */
  public function countAttempts(string $organizationId): int;

  /**
   * Caller owns the main transaction; this reserves the action row lock through commit.
   */
  public function reserveRetry(string $organizationId, string $runId, string $attemptId, string $actorUserId): AutomationRetry;
  // #endregion
}
