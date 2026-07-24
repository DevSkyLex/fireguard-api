<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO ConversationActivityBucketOutput.
 *
 * A single daily bucket of the conversation activity heatmap
 * (`GET /conversations/{conversationId}/activity`).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ConversationActivityBucketOutput
{
  /**
   * Property bucket.
   *
   * The bucket's calendar day, UTC, `Y-m-d` (e.g. `2026-07-21`). Doubles as
   * the API Platform collection item identifier — bucket days are unique
   * within one response.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $bucket = '';

  /**
   * Property count.
   *
   * The number of messages posted in the conversation on that day (UTC),
   * including threaded replies and later-tombstoned messages. `0` for an
   * empty bucket — every requested bucket is always present, never omitted.
   *
   * @since 1.0.0
   */
  public int $count = 0;
}
