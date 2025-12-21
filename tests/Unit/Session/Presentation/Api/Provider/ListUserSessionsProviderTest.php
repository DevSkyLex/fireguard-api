<?php

declare(strict_types=1);

namespace Tests\Session\Presentation\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\UseCase\Query\ListUserSessions\ListUserSessionsHandler;
use Session\Domain\Model\Session;
use Session\Domain\ValueObject\SessionId;
use Session\Presentation\Api\Provider\ListUserSessionsProvider;
use Shared\Domain\ValueObject\IpAddress;
use Shared\Domain\ValueObject\UserAgent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

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
    $repository = $this->createMock(SessionRepositoryPort::class);
    $handler = new ListUserSessionsHandler(sessionRepository: $repository);

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListUserSessionsProvider(
      handler: $handler,
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
    $session = Session::create(
      id: new SessionId($sessionId),
      userId: 'user-123',
      ipAddress: new IpAddress('192.168.1.1'),
      userAgent: new UserAgent('Mozilla/5.0'),
    );

    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findActiveByUserId')
      ->with('user-123')
      ->willReturn([$session]);

    $handler = new ListUserSessionsHandler(sessionRepository: $repository);

    $user = $this->createMock(UserInterface::class);
    $user->expects(self::once())
      ->method('getUserIdentifier')
      ->willReturn('user-123');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $provider = new ListUserSessionsProvider(
      handler: $handler,
      security: $security,
    );

    $result = $provider->provide(
      operation: new GetCollection(),
      uriVariables: [],
      context: ['request' => null],
    );

    self::assertCount(1, $result);
    self::assertEquals($sessionId, $result[0]->id);
  }
  // #endregion
}
