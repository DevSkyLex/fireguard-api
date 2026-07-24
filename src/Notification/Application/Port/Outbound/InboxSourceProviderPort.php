<?php

declare(strict_types=1);

namespace Notification\Application\Port\Outbound;

use DateTimeImmutable;
use Notification\Application\Contract\Inbox\InboxItem;

/**
 * Port InboxSourceProviderPort.
 *
 * Cross-module seam tagged `inbox.source_provider` — a direct clone of the
 * `messaging.subject_resolver` pattern
 * ({@see \Messaging\Application\Port\Outbound\MessagingSubjectResolverPort}).
 * Each source module (Notification for its own notifications, and
 * eventually Messaging for mentions/direct messages/thread replies) hosts
 * one adapter under its own `Infrastructure/Adapter/Notification/`,
 * registered with this tag in its own `config/modules/<module>.yaml`.
 * Notification consumes every tagged adapter through
 * {@see \Notification\Application\Service\InboxAggregator}
 * (`!tagged_iterator inbox.source_provider`), never depending on a source
 * module's Domain or Infrastructure directly.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InboxSourceProviderPort
{
  // #region Methods
  /**
   * Method sourceKey.
   *
   * A short, stable identifier for this source (e.g. `notification`,
   * `messaging.mention`). Used to label items and as the aggregator's
   * deterministic tie-breaker when two items share the exact same
   * `occurredAt`.
   *
   * @since 1.0.0
   *
   * @return string the source key
   */
  public function sourceKey(): string;

  /**
   * Method fetch.
   *
   * Returns this source's most recent items for one user, bounded to at
   * most `$limit` items, ordered by `occurredAt` descending. Implementations
   * must never load a whole source: `$limit` is a hard cap on the query,
   * not a post-fetch truncation.
   *
   * Implementations must never throw for expected "nothing to return"
   * cases (return an empty array instead); {@see
   * \Notification\Application\Service\InboxAggregator} still wraps every
   * call defensively so an unexpected failure degrades to "this source
   * contributed nothing" rather than failing the whole inbox request, but a
   * well-behaved provider should not rely on that safety net.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier the feed is scoped to
   * @param string|null $organizationId when provided, restricts items to this organization; when null, every organization (and account-level items) may be returned
   * @param DateTimeImmutable|null $before the cursor: when provided, only items with `occurredAt` strictly before this instant are returned; when null, the most recent items are returned
   * @param int $limit the maximum number of items to return
   *
   * @return list<InboxItem> the source's items, ordered by `occurredAt` descending
   */
  public function fetch(string $userId, ?string $organizationId, ?DateTimeImmutable $before, int $limit): array;

  /**
   * Method countUnread.
   *
   * Returns this source's unread item count for one user, optionally scoped
   * to one organization. Unlike {@see fetch()}, this is not `$limit`-bounded:
   * implementations must return the true count (a single aggregate query),
   * not the count of a bounded page — the sidebar/inbox badge must never
   * under-report just because a source caps its feed page size.
   *
   * Implementations must never throw for expected "nothing to count" cases
   * (return `0` instead); {@see \Notification\Application\Service\InboxAggregator}
   * still wraps every call defensively so an unexpected failure degrades to
   * "this source contributes 0" rather than failing the whole unread-count
   * request, but a well-behaved provider should not rely on that safety net.
   *
   * @since 1.1.0
   *
   * @param string $userId the user identifier the count is scoped to
   * @param string|null $organizationId when provided, restricts the count to this organization; when null, every organization (and account-level items) is counted
   *
   * @return int the unread item count for this source
   */
  public function countUnread(string $userId, ?string $organizationId): int;
  // #endregion
}
