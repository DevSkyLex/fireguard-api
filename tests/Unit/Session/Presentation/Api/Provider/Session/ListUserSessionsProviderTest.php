<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Presentation\Api\Provider\Session;

use ApiPlatform\Metadata\GetCollection;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Session\Application\UseCase\Query\Session\GetSession\GetSessionResult;
use Session\Application\UseCase\Query\Session\ListUserSessions\ListUserSessionsQuery;
use Session\Presentation\Api\Provider\Session\ListUserSessionsProvider;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

use function iterator_to_array;

/**
 * Test ListUserSessionsProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ListUserSessionsProvider::class)]
final class ListUserSessionsProviderTest extends TestCase
{
  // #region Methods
  /**
   * Method testProvideReturnsEmptyArrayWhenNotAuthenticated.
   *
   * Test that provide returns empty array when user is not authenticated.
   */
  #[Test]
  public function testProvideReturnsEmptyArrayWhenNotAuthenticated(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListUserSessionsProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: [],
      context: [],
    );
  }

  /**
   * Method testProvideReturnsSessionsForAuthenticatedUser.
   *
   * Test that provide returns sessions for authenticated user.
   */
  #[Test]
  public function testProvideReturnsSessionsForAuthenticatedUser(): void
  {
    $sessionId = '123e4567-e89b-12d3-a456-426614174000';
    $result = new PaginatedResult(
      items: [
        new GetSessionResult(
          sessionId: $sessionId,
          userId: 'user-123',
          ipAddress: '192.168.1.1',
          userAgent: 'Mozilla/5.0',
          deviceType: 'desktop',
          browser: null,
          operatingSystem: null,
          country: null,
          city: null,
          rememberMe: true,
          createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
          lastActivityAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
          isRevoked: false,
        ),
      ],
      total: 1,
      limit: 1,
      offset: 0,
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(ListUserSessionsQuery::class))
      ->willReturn($result);

    $user = $this->createMock(UserInterface::class);
    $user->expects(self::once())
      ->method('getUserIdentifier')
      ->willReturn('user-123');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $provider = new ListUserSessionsProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $result = $provider->provide(
      operation: new GetCollection(),
      uriVariables: [],
      context: ['request' => null],
    );

    $items = iterator_to_array($result);
    self::assertCount(1, $items);
    self::assertEquals($sessionId, $items[0]->id);
  }
  // #endregion
}
