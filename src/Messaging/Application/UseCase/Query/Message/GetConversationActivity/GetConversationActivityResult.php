<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Message\GetConversationActivity;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetConversationActivityResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetConversationActivityResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<array{bucket: string, count: int}> $buckets the zero-filled daily buckets, oldest first, ending today (UTC)
   */
  public function __construct(
    public array $buckets,
  ) {
  }
}
