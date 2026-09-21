<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\Service;

use DateTimeImmutable;
use Notification\Application\Contract\Inbox\{InboxCursor, InboxItem};
use Notification\Application\Port\Outbound\InboxSourceProviderPort;
use Throwable;

use function array_filter;
use function array_slice;
use function array_values;
use function count;
use function usort;

/**
 * Fake FakeInboxSourceProvider.
 *
 * In-test double for {@see InboxSourceProviderPort}: returns a fixed list of
 * items (or throws a fixed exception) and records the arguments of its last
 * `fetch()` call, so tests can assert both the aggregator's merge behavior
 * and what it forwards to each source.
 *
 * Lives in its own file rather than beside a test case: three test classes
 * share it, and a parallel worker only loads the files it runs, so a class
 * declared inside a sibling test would be missing.
 *
 * @category Fake
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FakeInboxSourceProvider implements InboxSourceProviderPort
{
  /**
   * @var array{0: string, 1: ?string, 2: ?DateTimeImmutable, 3: int}|null
   */
  public ?array $lastCallArguments = null;

  /**
   * @var array{0: string, 1: ?string}|null
   */
  public ?array $lastCountUnreadCallArguments = null;

  /**
   * @param list<InboxItem> $items
   */
  public function __construct(
    private readonly string $key,
    private readonly array $items,
    private readonly ?Throwable $throws = null,
    private readonly ?int $unreadCount = null,
  ) {
  }

  public function sourceKey(): string
  {
    return $this->key;
  }

  public function fetch(string $userId, ?string $organizationId, ?DateTimeImmutable $before, int $limit, ?InboxCursor $cursor = null): array
  {
    $this->lastCallArguments = [$userId, $organizationId, $before, $limit];

    if (null !== $this->throws) {
      throw $this->throws;
    }

    $items = array_values(array_filter(
      $this->items,
      static fn (InboxItem $item): bool => (null === $before || $item->occurredAt < $before) && (null === $cursor || $cursor->precedes($item)),
    ));
    usort($items, static fn (InboxItem $a, InboxItem $b): int => ($b->occurredAt <=> $a->occurredAt) ?: ($a->sourceKey <=> $b->sourceKey) ?: ($a->id <=> $b->id));

    return array_slice($items, 0, $limit);
  }

  public function countUnread(string $userId, ?string $organizationId): int
  {
    $this->lastCountUnreadCallArguments = [$userId, $organizationId];

    if (null !== $this->throws) {
      throw $this->throws;
    }

    return $this->unreadCount ?? count(array_filter($this->items, static fn (InboxItem $item): bool => !$item->isRead));
  }
}
