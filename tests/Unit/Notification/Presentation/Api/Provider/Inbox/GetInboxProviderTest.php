<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Presentation\Api\Provider\Inbox;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Notification\Application\Contract\Inbox\InboxItem;
use Notification\Application\UseCase\Query\Inbox\ListInboxItems\{ListInboxItemsQuery, ListInboxItemsResult};
use Notification\Presentation\Api\Dto\Output\Inbox\InboxOutput;
use Notification\Presentation\Api\Provider\Inbox\GetInboxProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

#[CoversClass(GetInboxProvider::class)]
final class GetInboxProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new GetInboxProvider(queryBus: $queryBus, security: $security, requestStack: $requestStack);

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get());
  }

  #[Test]
  public function testProvideMapsFiltersAndTheAggregatedResultIntoTheOutputEnvelope(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442800'));

    $item = new InboxItem(
      sourceKey: 'notification',
      id: 'n-1',
      kind: 'notification',
      title: 'You were invited',
      snippet: 'Join the organization.',
      occurredAt: new DateTimeImmutable('2026-07-18T09:00:00+00:00'),
      isRead: false,
      organizationId: '550e8400-e29b-41d4-a716-446655442899',
      targetType: 'notification',
      targetId: 'n-1',
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListInboxItemsQuery $query): bool {
        return '550e8400-e29b-41d4-a716-446655442800' === $query->userId
          && '550e8400-e29b-41d4-a716-446655442899' === $query->organizationId
          && $query->before instanceof DateTimeImmutable
          && '2026-07-18T08:00:00+00:00' === $query->before->format('c')
          && 10 === $query->limit;
      }))
      ->willReturn(new ListInboxItemsResult(items: [$item], nextCursor: '2026-07-18T09:00:00+00:00', hasMore: true));

    $request = new Request();
    $request->query->set('organization', '550e8400-e29b-41d4-a716-446655442899');
    $request->query->set('before', '2026-07-18T08:00:00+00:00');
    $request->query->set('limit', '10');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new GetInboxProvider(queryBus: $queryBus, security: $security, requestStack: $requestStack);

    $output = $provider->provide(new Get());

    self::assertInstanceOf(InboxOutput::class, $output);
    self::assertCount(1, $output->items);
    self::assertSame('n-1', $output->items[0]->id);
    self::assertSame('notification', $output->items[0]->sourceKey);
    self::assertSame('You were invited', $output->items[0]->title);
    self::assertSame('2026-07-18T09:00:00+00:00', $output->items[0]->occurredAt);
    self::assertSame('2026-07-18T09:00:00+00:00', $output->nextCursor);
    self::assertTrue($output->hasMore);
  }

  #[Test]
  public function testProvideThrowsBadRequestOnAnUnparsableCursor(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442801'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $request = new Request();
    $request->query->set('before', 'not-a-date');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new GetInboxProvider(queryBus: $queryBus, security: $security, requestStack: $requestStack);

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new Get());
  }

  #[Test]
  public function testProvideFallsBackWhenTheCursorIsAbsentAndTheLimitIsNotNumeric(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442802'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListInboxItemsQuery $query): bool {
        return null === $query->before
          && null === $query->organizationId
          && 20 === $query->limit;
      }))
      ->willReturn(new ListInboxItemsResult(items: [], nextCursor: null, hasMore: false));

    $request = new Request();
    $request->query->set('limit', 'not-a-number');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new GetInboxProvider(queryBus: $queryBus, security: $security, requestStack: $requestStack);

    $output = $provider->provide(new Get());

    self::assertSame([], $output->items);
    self::assertNull($output->nextCursor);
    self::assertFalse($output->hasMore);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
