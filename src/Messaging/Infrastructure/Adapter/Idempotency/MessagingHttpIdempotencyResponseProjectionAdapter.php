<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Adapter\Idempotency;

use Messaging\Application\UseCase\Command\Message\AddReaction\{AddReactionCommand, AddReactionResult};
use Messaging\Application\UseCase\Command\Message\EditMessage\{EditMessageCommand, EditMessageResult};
use Messaging\Application\UseCase\Command\Message\PinMessage\{PinMessageCommand, PinMessageResult};
use Messaging\Application\UseCase\Command\Message\PostMessage\{PostMessageCommand, PostMessageResult};
use Messaging\Application\UseCase\Command\Message\PostReply\{PostReplyCommand, PostReplyResult};
use Messaging\Application\UseCase\Command\Message\SaveMessage\{SaveMessageCommand, SaveMessageResult};
use Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesQuery;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyResponseProjectionPort;

use function in_array;

/**
 * Class MessagingHttpIdempotencyResponseProjectionAdapter
 *
 * Constructs the exact presentation reads inside the owning transaction before its receipt is completed.
 *
 * @category Adapter
 */
final readonly class MessagingHttpIdempotencyResponseProjectionAdapter implements HttpIdempotencyResponseProjectionPort
{
  /**
   * {@inheritdoc}
   */
  public function supports(string $operation): bool
  {
    return in_array($operation, ['_api_/conversations/{conversationId}/messages_post', 'put_message', '_api_/messages/{id}_patch', 'pin_message', 'add_reaction', 'post_reply', 'save_message'], true);
  }

  /**
   * {@inheritdoc}
   */
  public function queries(string $operation, CommandMessage $command, ResultMessage $result): array
  {
    $valid = match ($operation) {
      '_api_/conversations/{conversationId}/messages_post', 'put_message' => $command instanceof PostMessageCommand && $result instanceof PostMessageResult,
      '_api_/messages/{id}_patch' => $command instanceof EditMessageCommand && $result instanceof EditMessageResult,
      'pin_message' => $command instanceof PinMessageCommand && $result instanceof PinMessageResult,
      'add_reaction' => $command instanceof AddReactionCommand && $result instanceof AddReactionResult,
      'post_reply' => $command instanceof PostReplyCommand && $result instanceof PostReplyResult,
      'save_message' => $command instanceof SaveMessageCommand && $result instanceof SaveMessageResult,
      default => false,
    };
    if (!$valid || !($result instanceof PostMessageResult || $result instanceof EditMessageResult || $result instanceof PinMessageResult || $result instanceof AddReactionResult || $result instanceof PostReplyResult || $result instanceof SaveMessageResult)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return [new EnrichMessagesQuery([$result->message], $result->currentMemberId)];
  }
}
