<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Presentation\Api\Provider\Notification;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Notification\Application\UseCase\Query\Notification\GetUnreadNotificationsCount\{GetUnreadNotificationsCountQuery, GetUnreadNotificationsCountResult};
use Notification\Presentation\Api\Dto\Output\Notification\UnreadNotificationsCountOutput;
use Notification\Presentation\Api\Provider\Notification\GetUnreadNotificationsCountProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(GetUnreadNotificationsCountProvider::class)]
final class GetUnreadNotificationsCountProviderTest extends TestCase
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

    $provider = new GetUnreadNotificationsCountProvider(
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
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442700'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetUnreadNotificationsCountQuery $query): bool {
        return '550e8400-e29b-41d4-a716-446655442700' === $query->userId
          && null === $query->organizationId;
      }))
      ->willReturn(new GetUnreadNotificationsCountResult(count: 5));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new GetUnreadNotificationsCountProvider(
      queryBus: $queryBus,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $provider->provide(new Get());

    self::assertInstanceOf(UnreadNotificationsCountOutput::class, $output);
    self::assertSame(5, $output->count);
  }

  #[Test]
  public function testProvidePassesOrganizationQueryFilterToQuery(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442701'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetUnreadNotificationsCountQuery $query): bool {
        return '550e8400-e29b-41d4-a716-446655442799' === $query->organizationId;
      }))
      ->willReturn(new GetUnreadNotificationsCountResult(count: 1));

    $request = new Request();
    $request->query->set('organization', '550e8400-e29b-41d4-a716-446655442799');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new GetUnreadNotificationsCountProvider(
      queryBus: $queryBus,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $provider->provide(new Get());

    self::assertSame(1, $output->count);
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
