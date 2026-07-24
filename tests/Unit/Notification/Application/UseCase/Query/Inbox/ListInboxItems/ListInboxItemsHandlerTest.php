<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\UseCase\Query\Inbox\ListInboxItems;

use DateTimeImmutable;
use Notification\Application\Contract\Inbox\InboxItem;
use Notification\Application\Service\InboxAggregator;
use Notification\Application\UseCase\Query\Inbox\ListInboxItems\{ListInboxItemsHandler, ListInboxItemsQuery, ListInboxItemsResult};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
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
  public function testInvokeClampsAnOversizedLimitToTheMaximumPageSize(): void
  {
    $provider = new FakeInboxSourceProvider('notification', []);

    $handler = new ListInboxItemsHandler(new InboxAggregator(
      providers: [$provider],
      logger: $this->createStub(LoggerPort::class),
    ));

    $handler->__invoke(new ListInboxItemsQuery(userId: 'user-1', limit: 999));

    self::assertSame(50, $provider->lastCallArguments[3] ?? null);
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

    self::assertSame(1, $provider->lastCallArguments[3] ?? null);
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
