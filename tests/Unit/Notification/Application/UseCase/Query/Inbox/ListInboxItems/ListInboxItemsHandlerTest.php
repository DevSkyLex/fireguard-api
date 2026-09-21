<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\UseCase\Query\Inbox\ListInboxItems;

use DateTimeImmutable;
use Notification\Application\Contract\Inbox\{InboxCursor, InboxItem};
use Notification\Application\Service\InboxAggregator;
use Notification\Application\UseCase\Query\Inbox\ListInboxItems\{ListInboxItemsHandler, ListInboxItemsQuery, ListInboxItemsResult};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\LoggerPort;
use Tests\Unit\Notification\Application\Service\FakeInboxSourceProvider;

/**
 * Test ListInboxItemsHandlerTest.
 *
 * Exercises the handler against a real {@see InboxAggregator} composed of
 * fake providers (the aggregator is `final`, so it is a real collaborator
 * here rather than a mock — the handler's own responsibility is limit
 * clamping and `nextCursor`/`hasMore` derivation).
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListInboxItemsHandler::class)]
final class ListInboxItemsHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsNextCursorAndHasMoreWhenThePageIsFull(): void
  {
    $provider = new FakeInboxSourceProvider('notification', [
      $this->item('n-1', '2026-07-18T09:03:00+00:00'),
      $this->item('n-2', '2026-07-18T09:02:00+00:00'),
      $this->item('n-3', '2026-07-18T09:01:00+00:00'),
    ]);

    $handler = new ListInboxItemsHandler(new InboxAggregator(
      providers: [$provider],
      logger: $this->createStub(LoggerPort::class),
    ));

    $result = $handler->__invoke(new ListInboxItemsQuery(userId: 'user-1', limit: 2));

    self::assertInstanceOf(ListInboxItemsResult::class, $result);
    self::assertCount(2, $result->items);
    self::assertTrue($result->hasMore);
    self::assertSame('2026-07-18T09:02:00+00:00', $result->nextCursor);
  }

  #[Test]
  public function testInvokeReturnsNullNextCursorWhenThereIsNoFurtherPage(): void
  {
    $provider = new FakeInboxSourceProvider('notification', [
      $this->item('n-1', '2026-07-18T09:00:00+00:00'),
    ]);

    $handler = new ListInboxItemsHandler(new InboxAggregator(
      providers: [$provider],
      logger: $this->createStub(LoggerPort::class),
    ));

    $result = $handler->__invoke(new ListInboxItemsQuery(userId: 'user-1', limit: 20));

    self::assertFalse($result->hasMore);
    self::assertNull($result->nextCursor);
  }

  #[Test]
  public function testPartialSourcesCannotAdvanceTheCompositeCursor(): void
  {
    $sources = [
      new FakeInboxSourceProvider('notification', [$this->item('a', '2026-07-18T09:00:00+00:00'), $this->item('b', '2026-07-18T08:00:00+00:00')]),
      new FakeInboxSourceProvider('messaging.mention', [], new RuntimeException('unavailable')),
    ];
    $handler = new ListInboxItemsHandler(new InboxAggregator($sources, $this->createStub(LoggerPort::class)));
    $result = $handler(new ListInboxItemsQuery('user-1', limit: 1));
    self::assertFalse($result->complete);
    self::assertCount(1, $result->items);
    self::assertTrue($result->hasMore);
    self::assertNull($result->nextPageCursor);
  }

  #[Test]
  public function testInvokeClampsAnOversizedLimitToTheMaximumPageSize(): void
  {
    $provider = new FakeInboxSourceProvider('notification', []);

    $handler = new ListInboxItemsHandler(new InboxAggregator(
      providers: [$provider],
      logger: $this->createStub(LoggerPort::class),
    ));

    $handler->__invoke(new ListInboxItemsQuery(userId: 'user-1', limit: 999));

    self::assertSame(51, $provider->lastCallArguments[3] ?? null);
  }

  #[Test]
  public function testInvokeClampsANonPositiveLimitToOne(): void
  {
    $provider = new FakeInboxSourceProvider('notification', []);

    $handler = new ListInboxItemsHandler(new InboxAggregator(
      providers: [$provider],
      logger: $this->createStub(LoggerPort::class),
    ));

    $handler->__invoke(new ListInboxItemsQuery(userId: 'user-1', limit: 0));

    self::assertSame(2, $provider->lastCallArguments[3] ?? null);
  }

  #[Test]
  public function testCompositeCursorVisitsEveryTimestampTieAcrossSources(): void
  {
    $sameInstant = new DateTimeImmutable('2026-09-20T10:00:00.123456+02:00');
    $sources = [];
    foreach (['notification', 'messaging.mention'] as $source) {
      $items = [];
      foreach (['c', 'a', 'b'] as $id) {
        $items[] = new InboxItem($source, $id, $source, $id, null, $sameInstant, false, 'org', $source, $id);
      }
      $sources[] = new FakeInboxSourceProvider($source, $items);
    }
    $handler = new ListInboxItemsHandler(new InboxAggregator($sources, $this->createStub(LoggerPort::class)));
    $cursor = null;
    $seen = [];
    for ($page = 0; $page < 3; ++$page) {
      $result = $handler(new ListInboxItemsQuery('user', 'org', limit: 2, cursor: $cursor));
      foreach ($result->items as $item) {
        $seen[] = $item->sourceKey . ':' . $item->id;
      }
      $cursor = null === $result->nextPageCursor ? null : InboxCursor::decode($result->nextPageCursor);
      if (null !== $cursor) {
        self::assertSame('123456', $cursor->occurredAt->format('u'));
        self::assertSame('2026-09-20 08:00:00.123456', $cursor->databaseInstant());
      }
    }
    self::assertSame(['messaging.mention:a', 'messaging.mention:b', 'messaging.mention:c', 'notification:a', 'notification:b', 'notification:c'], $seen);
    self::assertNull($cursor);
    self::assertFalse($result->hasMore);
  }

  private function item(string $id, string $occurredAt): InboxItem
  {
    return new InboxItem(
      sourceKey: 'notification',
      id: $id,
      kind: 'notification',
      title: 'Title ' . $id,
      snippet: null,
      occurredAt: new DateTimeImmutable($occurredAt),
      isRead: false,
      organizationId: null,
      targetType: 'notification',
      targetId: $id,
    );
  }
}
