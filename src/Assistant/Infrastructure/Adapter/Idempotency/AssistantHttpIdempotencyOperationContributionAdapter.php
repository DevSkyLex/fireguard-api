<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class AssistantHttpIdempotencyOperationContributionAdapter
 *
 * Declares exact owner command, database, transaction and response schemas for durable HTTP attempts.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author FireGuard contributors
 */
final readonly class AssistantHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'assistant_cancel_generation',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Assistant\Application\UseCase\Command\Message\ControlAssistantAttempt\ControlAssistantAttemptCommand::class,
        resultClass: \Assistant\Application\UseCase\Command\Message\ControlAssistantAttempt\ControlAssistantAttemptResult::class,
        contractClasses: [\Assistant\Application\Contract\Message\AssistantMessageView::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'message.id',
        resourceKind: 'assistant_message',
      ),
      new HttpIdempotencyOperation(
        name: 'assistant_retry_generation',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Assistant\Application\UseCase\Command\Message\ControlAssistantAttempt\ControlAssistantAttemptCommand::class,
        resultClass: \Assistant\Application\UseCase\Command\Message\ControlAssistantAttempt\ControlAssistantAttemptResult::class,
        contractClasses: [\Assistant\Application\Contract\Message\AssistantMessageView::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'message.id',
        resourceKind: 'assistant_message',
      ),
      new HttpIdempotencyOperation(
        name: 'startAssistantThread',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Assistant\Application\UseCase\Command\Thread\StartAssistantThread\StartAssistantThreadCommand::class,
        resultClass: \Assistant\Application\UseCase\Command\Thread\StartAssistantThread\StartAssistantThreadResult::class,
        contractClasses: [\Assistant\Application\Contract\Thread\AssistantThreadView::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'thread.id',
        resourceKind: 'assistant_thread',
      ),
      new HttpIdempotencyOperation(
        name: 'askAssistantQuestion',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Assistant\Application\UseCase\Command\Message\AskAssistantQuestion\AskAssistantQuestionCommand::class,
        resultClass: \Assistant\Application\UseCase\Command\Message\AskAssistantQuestion\AskAssistantQuestionResult::class,
        contractClasses: [\Assistant\Application\Contract\Message\AssistantMessageView::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'assistantMessage.id',
        resourceKind: 'assistant_message',
      ),
    ];
  }
  // #endregion
}
