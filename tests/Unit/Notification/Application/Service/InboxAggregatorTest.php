<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\Service;

use DateTimeImmutable;
use Notification\Application\Contract\Inbox\InboxItem;
use Notification\Application\Service\{InboxAggregationResult, InboxAggregator};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\LoggerPort;

use function array_map;

/**
 * Test InboxAggregatorTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InboxAggregator::class)]
final class InboxAggregatorTest extends TestCase
{
  #[Test]
  public function testAggregateMergesAndOrdersEveryProviderByOccurredAtDescending(): void
  {
    $notificationSource = new FakeInboxSourceProvider('notification', [
      $this->item('notification', 'n-1', '2026-07-18T09:00:00+00:00'),
      $this->item('notification', 'n-2', '2026-07-18T07:00:00+00:00'),
    ]);
    $messagingSource = new FakeInboxSourceProvider('messaging.mention', [
      $this->item('messaging.mention', 'm-1', '2026-07-18T08:00:00+00:00'),
    ]);

    $aggregator = new InboxAggregator(
      providers: [$notificationSource, $messagingSource],
      logger: $this->createStub(LoggerPort::class),
    );

    $result = $aggregator->aggregate(userId: 'user-1', organizationId: null, before: null, limit: 10);

    self::assertInstanceOf(InboxAggregationResult::class, $result);
    self::assertSame(['n-1', 'm-1', 'n-2'], $this->ids($result->items));
  }

  #[Test]
  public function testAggregateBreaksOccurredAtTiesBySourceKeyThenId(): void
  {
    $sameInstant = '2026-07-18T09:00:00+00:00';

    $sourceB = new FakeInboxSourceProvider('sourceB', [
      $this->item('sourceB', 'z', $sameInstant),
      $this->item('sourceB', 'a', $sameInstant),
    ]);
    $sourceA = new FakeInboxSourceProvider('sourceA', [
      $this->item('sourceA', 'x', $sameInstant),
    ]);

    // Registration order deliberately does NOT match sourceKey order, to
    // prove the tie-breaker (not insertion order) drives the result.
    $aggregator = new InboxAggregator(
      providers: [$sourceB, $sourceA],
      logger: $this->createStub(LoggerPort::class),
    );

    $result = $aggregator->aggregate(userId: 'user-1', organizationId: null, before: null, limit: 10);

    // sourceA < sourceB (ascending), and within sourceB, 'a' < 'z' (ascending).
    self::assertSame(['sourceA:x', 'sourceB:a', 'sourceB:z'], $this->sourceQualifiedIds($result->items));
  }

  #[Test]
  public function testAggregateForwardsTheSameCursorOrganizationAndLimitToEveryProvider(): void
  {
    $before = new DateTimeImmutable('2026-07-18T09:00:00+00:00');

    $provider = new FakeInboxSourceProvider('notification', []);

    $aggregator = new InboxAggregator(
      providers: [$provider],
      logger: $this->createStub(LoggerPort::class),
    );

    $aggregator->aggregate(userId: 'user-42', organizationId: 'org-7', before: $before, limit: 15);

    self::assertSame(['user-42', 'org-7', $before, 15], $provider->lastCallArguments);
  }

  #[Test]
  public function testAggregateTruncatesTheMergedResultToThePageSize(): void
  {
    $provider = new FakeInboxSourceProvider('notification', [
      $this->item('notification', 'n-1', '2026-07-18T09:03:00+00:00'),
      $this->item('notification', 'n-2', '2026-07-18T09:02:00+00:00'),
      $this->item('notification', 'n-3', '2026-07-18T09:01:00+00:00'),
    ]);

    $aggregator = new InboxAggregator(
      providers: [$provider],
      logger: $this->createStub(LoggerPort::class),
    );

    $result = $aggregator->aggregate(userId: 'user-1', organizationId: null, before: null, limit: 2);

    self::assertCount(2, $result->items);
    self::assertSame(['n-1', 'n-2'], $this->ids($result->items));
    self::assertTrue($result->hasMore);
  }

  #[Test]
  public function testAggregateReportsNoMoreWhenNoSourceIsAtCapacity(): void
  {
    $provider = new FakeInboxSourceProvider('notification', [
      $this->item('notification', 'n-1', '2026-07-18T09:00:00+00:00'),
    ]);

    $aggregator = new InboxAggregator(
      providers: [$provider],
      logger: $this->createStub(LoggerPort::class),
    );

    $result = $aggregator->aggregate(userId: 'user-1', organizationId: null, before: null, limit: 10);

    self::assertFalse($result->hasMore);
  }

  #[Test]
  public function testAggregateDegradesAFailingProviderToAnEmptyContributionAndLogsIt(): void
  {
    $healthySource = new FakeInboxSourceProvider('notification', [
      $this->item('notification', 'n-1', '2026-07-18T09:00:00+00:00'),
    ]);
    $failingSource = new FakeInboxSourceProvider('messaging.mention', [], new RuntimeException('source unavailable'));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('error')
      ->with(
        self::stringContains('degrading'),
        self::callback(static function (array $context): bool {
          return 'messaging.mention' === ($context['sourceKey'] ?? null)
            && 'source unavailable' === ($context['error'] ?? null);
        }),
      );

    $aggregator = new InboxAggregator(
      providers: [$healthySource, $failingSource],
      logger: $logger,
    );

    $result = $aggregator->aggregate(userId: 'user-1', organizationId: null, before: null, limit: 10);

    self::assertSame(['n-1'], $this->ids($result->items));
    self::assertFalse($result->hasMore);
  }

  #[Test]
  public function testCountUnreadSumsEveryProviderAndForwardsUserAndOrganization(): void
  {
    $notificationSource = new FakeInboxSourceProvider('notification', [], unreadCount: 3);
    $messagingSource = new FakeInboxSourceProvider('messaging.mention', [], unreadCount: 2);

    $aggregator = new InboxAggregator(
      providers: [$notificationSource, $messagingSource],
      logger: $this->createStub(LoggerPort::class),
    );

    $total = $aggregator->countUnread(userId: 'user-1', organizationId: 'org-7');

    self::assertSame(5, $total);
    self::assertSame(['user-1', 'org-7'], $notificationSource->lastCountUnreadCallArguments);
    self::assertSame(['user-1', 'org-7'], $messagingSource->lastCountUnreadCallArguments);
  }

  #[Test]
  public function testCountUnreadDegradesAFailingProviderToZeroAndLogsIt(): void
  {
    $healthySource = new FakeInboxSourceProvider('notification', [], unreadCount: 4);
    $failingSource = new FakeInboxSourceProvider('messaging.mention', [], new RuntimeException('source unavailable'));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('error')
      ->with(
        self::stringContains('degrading'),
        self::callback(static function (array $context): bool {
          return 'messaging.mention' === ($context['sourceKey'] ?? null)
            && 'source unavailable' === ($context['error'] ?? null);
        }),
      );

    $aggregator = new InboxAggregator(
      providers: [$healthySource, $failingSource],
      logger: $logger,
    );

    $total = $aggregator->countUnread(userId: 'user-1', organizationId: null);

    self::assertSame(4, $total);
  }

  private function item(string $sourceKey, string $id, string $occurredAt): InboxItem
  {
    return new InboxItem(
      sourceKey: $sourceKey,
      id: $id,
      kind: $sourceKey,
      title: 'Title ' . $id,
      snippet: null,
      occurredAt: new DateTimeImmutable($occurredAt),
      isRead: false,
      organizationId: null,
      targetType: $sourceKey,
      targetId: $id,
    );
  }

  /**
   * @param list<InboxItem> $items
   *
   * @return list<string>
   */
  private function ids(array $items): array
  {
    return array_map(static fn (InboxItem $item): string => $item->id, $items);
  }

  /**
   * @param list<InboxItem> $items
   *
   * @return list<string>
   */
  private function sourceQualifiedIds(array $items): array
  {
    return array_map(static fn (InboxItem $item): string => $item->sourceKey . ':' . $item->id, $items);
  }
}
