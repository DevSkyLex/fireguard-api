<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Presence;

/**
 * Contract MemberPresenceView.
 *
 * Read model for ONE organization member's online presence (L2.7),
 * resolved from a `Shared\Application\Port\Outbound\CachePort` multi-get —
 * there is no `messaging_presence` table. `lastSeenAt` is the ISO-8601
 * string stored verbatim by
 * {@see \Messaging\Application\UseCase\Command\Presence\PingPresence\PingPresenceHandler}
 * (never re-parsed to a `DateTimeImmutable` and back, to avoid a pointless
 * round trip). `online` is `true` exactly when the cache entry has not yet
 * expired (TTL 90s) — there is no separate "online" flag stored, the
 * presence of the key itself IS the online signal.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MemberPresenceView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $memberId the organization member identifier
   * @param bool $online whether the member's presence cache entry is currently live
   * @param ?string $lastSeenAt the ISO-8601 last-seen timestamp, when online
   */
  public function __construct(
    public string $memberId,
    public bool $online,
    public ?string $lastSeenAt,
  ) {
  }
  // #endregion
}
