<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Message\GetConversationActivity;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetConversationActivityQuery.
 *
 * Backs the conversation activity heatmap
 * (`GET /conversations/{conversationId}/activity`).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetConversationActivityQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $conversationId the conversation id value
   * @param int $buckets the number of trailing daily buckets to return, ending today (UTC)
   */
  public function __construct(
    public string $userId,
    public string $conversationId,
    public int $buckets = 26,
  ) {
  }
}
