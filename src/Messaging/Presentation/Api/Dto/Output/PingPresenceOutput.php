<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO PingPresenceOutput.
 *
 * `POST /api/presence/ping` response body (L2.7).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PingPresenceOutput
{
  /**
   * Property memberId.
   *
   * The acting member's own identifier, resolved server-side.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true, readable: true, writable: false)]
  public string $memberId = '';

  /**
   * Property lastSeenAt.
   *
   * The ISO-8601 timestamp written to the presence cache entry.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public string $lastSeenAt = '';
}
