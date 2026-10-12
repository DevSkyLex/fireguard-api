<?php

declare(strict_types=1);

namespace Automation\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class AutomationHttpIdempotencyOperationContributionAdapter
 *
 * Declares exact owner command, database, transaction and response schemas for durable HTTP attempts.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author FireGuard contributors
 */
final readonly class AutomationHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'automation_retry_attempt',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Automation\Application\UseCase\Command\RetryAutomationAttempt\RetryAutomationAttemptCommand::class,
        resultClass: \Automation\Application\UseCase\Command\RetryAutomationAttempt\RetryAutomationAttemptResult::class,
        contractClasses: [\Automation\Application\Contract\Run\AutomationAttemptView::class],
        statusCode: 202,
        requiredKey: true,
        resultIdentifierProperty: 'attempt.runId',
        resourceKind: 'automation_run',
      ),
    ];
  }
  // #endregion
}
