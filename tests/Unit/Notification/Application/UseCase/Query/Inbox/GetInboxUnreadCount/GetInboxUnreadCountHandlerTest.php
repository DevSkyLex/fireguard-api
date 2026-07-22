<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\UseCase\Query\Inbox\GetInboxUnreadCount;

use Notification\Application\Service\InboxAggregator;
use Notification\Application\UseCase\Query\Inbox\GetInboxUnreadCount\{GetInboxUnreadCountHandler, GetInboxUnreadCountQuery, GetInboxUnreadCountResult};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\LoggerPort;
use Tests\Unit\Notification\Application\Service\FakeInboxSourceProvider;

/**
 * Test GetInboxUnreadCountHandlerTest.
 *
 * Exercises the handler against a real {@see InboxAggregator} composed of
 * fake providers (the aggregator is `final`, so it is a real collaborator
 * here rather than a mock — the handler's own responsibility is delegating
 * to `InboxAggregator::countUnread()` and wrapping the result).
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetInboxUnreadCountHandler::class)]
final class GetInboxUnreadCountHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeSumsUnreadCountsAcrossEveryProvider(): void
  {
    $notificationSource = new FakeInboxSourceProvider('notification', [], unreadCount: 3);
    $messagingSource = new FakeInboxSourceProvider('messaging.mention', [], unreadCount: 2);

    $handler = new GetInboxUnreadCountHandler(new InboxAggregator(
      providers: [$notificationSource, $messagingSource],
      logger: $this->createStub(LoggerPort::class),
    ));

    $result = $handler->__invoke(new GetInboxUnreadCountQuery(userId: 'user-1'));

    self::assertInstanceOf(GetInboxUnreadCountResult::class, $result);
    self::assertSame(5, $result->unreadCount);
  }

  #[Test]
  public function testInvokeForwardsTheOrganizationFilterToEveryProvider(): void
  {
    $provider = new FakeInboxSourceProvider('notification', [], unreadCount: 1);

    $handler = new GetInboxUnreadCountHandler(new InboxAggregator(
      providers: [$provider],
      logger: $this->createStub(LoggerPort::class),
    ));

    $handler->__invoke(new GetInboxUnreadCountQuery(userId: 'user-1', organizationId: 'org-7'));

    self::assertSame(['user-1', 'org-7'], $provider->lastCountUnreadCallArguments);
  }

  #[Test]
  public function testInvokeReturnsZeroWhenThereAreNoRegisteredProviders(): void
  {
    $handler = new GetInboxUnreadCountHandler(new InboxAggregator(
      providers: [],
      logger: $this->createStub(LoggerPort::class),
    ));

    $result = $handler->__invoke(new GetInboxUnreadCountQuery(userId: 'user-1'));

    self::assertSame(0, $result->unreadCount);
  }
}
