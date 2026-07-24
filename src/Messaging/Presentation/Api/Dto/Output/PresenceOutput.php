<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO PresenceOutput.
 *
 * One row of `GET /api/presence`'s response (L2.7): a member's online
 * status, resolved from a `Shared\Application\Port\Outbound\CachePort`
 * multi-get, never from a database row.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PresenceOutput
{
  /**
   * Property memberId.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $memberId = '';

  /**
   * Property online.
   *
   * `true` when the member's presence cache entry has not expired (pinged
   * within the last 90 seconds).
   *
   * @since 1.0.0
   */
  public bool $online = false;

  /**
   * Property lastSeenAt.
   *
   * The ISO-8601 last-seen timestamp, when `online`; `null` otherwise
   * (never seen, or the entry has expired).
   *
   * @since 1.0.0
   */
  public ?string $lastSeenAt = null;
}
