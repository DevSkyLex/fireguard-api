<?php

declare(strict_types=1);

namespace Notification\Application\Contract\Inbox;

use DateTimeImmutable;

/**
 * Contract InboxItem.
 *
 * A single entry of the unified inbox feed (`inbox.source_provider` seam),
 * shaped so any provider module (Notification itself, and eventually
 * Messaging for mentions/direct messages/thread replies) can describe "one
 * thing needing the user's attention" without exposing its own Domain
 * types. Plain, framework-free contract type — never a provider module's
 * Domain object.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InboxItem
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $sourceKey the owning source's key (matches the emitting {@see \Notification\Application\Port\Outbound\InboxSourceProviderPort::sourceKey()}), used both to label the item and as the aggregator's tie-breaker
   * @param string $id the item identifier, unique within its source (not necessarily unique across sources)
   * @param string $kind a short discriminator for the item's nature (e.g. `notification`, `mention`, `direct_message`, `thread_reply`)
   * @param string $title a short human-readable headline for the item
   * @param string|null $snippet an optional longer excerpt/preview of the item's content
   * @param DateTimeImmutable $occurredAt when the item happened; drives feed ordering (descending) and is the cursor value a client echoes back via `before`
   * @param bool $isRead whether the authenticated user already read/acknowledged this item
   * @param string|null $organizationId the organization this item belongs to, when any
   * @param string $targetType the type of entity the client should navigate to (e.g. `notification`, `conversation`)
   * @param string $targetId the identifier of the entity the client should navigate to
   */
  public function __construct(
    public string $sourceKey,
    public string $id,
    public string $kind,
    public string $title,
    public ?string $snippet,
    public DateTimeImmutable $occurredAt,
    public bool $isRead,
    public ?string $organizationId,
    public string $targetType,
    public string $targetId,
  ) {
  }
  // #endregion
}
