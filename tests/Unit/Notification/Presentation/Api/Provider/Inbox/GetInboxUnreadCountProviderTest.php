<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Presentation\Api\Provider\Inbox;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Notification\Application\UseCase\Query\Inbox\GetInboxUnreadCount\{GetInboxUnreadCountQuery, GetInboxUnreadCountResult};
use Notification\Presentation\Api\Dto\Output\Inbox\InboxUnreadCountOutput;
use Notification\Presentation\Api\Provider\Inbox\GetInboxUnreadCountProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(GetInboxUnreadCountProvider::class)]
final class GetInboxUnreadCountProviderTest extends TestCase
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

    $provider = new GetInboxUnreadCountProvider(
      queryBus: $queryBus,
      security: $security,
      requestStack: $requestStack,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get());
  }

  #[Test]
  public function testProvideReturnsUnreadCountWithoutOrganizationFilter(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655443700'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetInboxUnreadCountQuery $query): bool {
        return '550e8400-e29b-41d4-a716-446655443700' === $query->userId
          && null === $query->organizationId;
      }))
      ->willReturn(new GetInboxUnreadCountResult(unreadCount: 5));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new GetInboxUnreadCountProvider(
      queryBus: $queryBus,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $provider->provide(new Get());

    self::assertInstanceOf(InboxUnreadCountOutput::class, $output);
    self::assertSame(5, $output->unreadCount);
  }

  #[Test]
  public function testProvidePassesOrganizationQueryFilterToQuery(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655443701'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetInboxUnreadCountQuery $query): bool {
        return '550e8400-e29b-41d4-a716-446655443799' === $query->organizationId;
      }))
      ->willReturn(new GetInboxUnreadCountResult(unreadCount: 1));

    $request = new Request();
    $request->query->set('organization', '550e8400-e29b-41d4-a716-446655443799');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new GetInboxUnreadCountProvider(
      queryBus: $queryBus,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $provider->provide(new Get());

    self::assertSame(1, $output->unreadCount);
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
