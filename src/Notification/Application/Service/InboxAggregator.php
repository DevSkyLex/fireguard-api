<?php

declare(strict_types=1);

namespace Notification\Application\Service;

use DateTimeImmutable;
use Notification\Application\Contract\Inbox\InboxItem;
use Notification\Application\Port\Outbound\InboxSourceProviderPort;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;

use function array_push;
use function array_slice;
use function count;
use function iterator_to_array;
use function usort;

/**
 * Service InboxAggregator.
 *
 * Merges every tagged `inbox.source_provider` adapter
 * ({@see InboxSourceProviderPort}) into a single reverse-chronological
 * feed, mirroring
 * {@see \Messaging\Application\Service\MessagingSubjectResolverRegistry}.
 * The aggregator never knows any concrete source: adding a source requires
 * zero edits here, only a new tagged adapter.
 *
 * Bounded merge: each provider is asked for at most `$limit` items (never a
 * whole source); results are merge-sorted by `occurredAt` descending with a
 * deterministic tie-breaker, then truncated to `$limit`.
 *
 * Resilience: a throwing (or otherwise misbehaving) provider degrades to
 * "contributed nothing" — it never fails the aggregate request — and the
 * failure is logged at `error` level so degradation stays visible instead
 * of silent.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InboxAggregator
{
  // #region Properties
  /**
   * Property providers.
   *
   * @since 1.0.0
   *
   * @var list<InboxSourceProviderPort>
   */
  private array $providers;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param iterable<InboxSourceProviderPort> $providers the tagged inbox source providers
   * @param LoggerPort $logger the logger port
   */
  public function __construct(
    iterable $providers,
    private LoggerPort $logger,
  ) {
    $this->providers = iterator_to_array($providers, false);
  }
  // #endregion

  // #region Methods
  /**
   * Method aggregate.
   *
   * Fetches, merges, sorts and truncates every source's contribution for
   * one user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier the feed is scoped to
   * @param string|null $organizationId optional organization filter, forwarded to every source
   * @param DateTimeImmutable|null $before the cursor: only items strictly before this instant are considered
   * @param int $limit the requested page size; also the per-source fetch bound
   *
   * @return InboxAggregationResult the aggregated page
   */
  public function aggregate(string $userId, ?string $organizationId, ?DateTimeImmutable $before, int $limit): InboxAggregationResult
  {
    /** @var list<InboxItem> $collected */
    $collected = [];
    $anySourceAtCapacity = false;

    foreach ($this->providers as $provider) {
      $items = $this->fetchFromProvider($provider, $userId, $organizationId, $before, $limit);

      if (count($items) >= $limit) {
        $anySourceAtCapacity = true;
      }

      array_push($collected, ...$items);
    }

    usort($collected, self::compare(...));

    $hasMore = count($collected) > $limit || $anySourceAtCapacity;

    return new InboxAggregationResult(
      items: array_slice($collected, 0, $limit),
      hasMore: $hasMore,
    );
  }

  /**
   * Method fetchFromProvider.
   *
   * Calls one source provider defensively: any throwable is caught, logged,
   * and translated into an empty contribution so one failing/slow source
   * never takes down the whole inbox request.
   *
   * @since 1.0.0
   *
   * @param InboxSourceProviderPort $provider the source provider
   * @param string $userId the user identifier
   * @param string|null $organizationId optional organization filter
   * @param DateTimeImmutable|null $before the cursor
   * @param int $limit the per-source fetch bound
   *
   * @return list<InboxItem> the source's items, or an empty list on failure
   */
  private function fetchFromProvider(
    InboxSourceProviderPort $provider,
    string $userId,
    ?string $organizationId,
    ?DateTimeImmutable $before,
    int $limit,
  ): array {
    try {
      return $provider->fetch($userId, $organizationId, $before, $limit);
    } catch (Throwable $exception) {
      $this->logger->error('Inbox source provider failed; degrading to an empty contribution.', [
        'sourceKey' => $provider->sourceKey(),
        'error' => $exception->getMessage(),
      ]);

      return [];
    }
  }

  /**
   * Method compare.
   *
   * Deterministic ordering: `occurredAt` descending first (most recent
   * first); items with the exact same instant are then ordered by
   * `sourceKey` ascending, then by `id` ascending, so ties never depend on
   * provider registration order or array insertion order.
   *
   * @since 1.0.0
   *
   * @param InboxItem $left the left-hand item
   * @param InboxItem $right the right-hand item
   *
   * @return int negative when `$left` sorts before `$right`, positive when after, zero when equal
   */
  private static function compare(InboxItem $left, InboxItem $right): int
  {
    $byOccurredAt = $right->occurredAt <=> $left->occurredAt;
    if (0 !== $byOccurredAt) {
      return $byOccurredAt;
    }

    $bySourceKey = $left->sourceKey <=> $right->sourceKey;
    if (0 !== $bySourceKey) {
      return $bySourceKey;
    }

    return $left->id <=> $right->id;
  }
  // #endregion
}
