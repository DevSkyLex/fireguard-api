<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Application\Service;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\Service\SessionStatusService;
use Session\Application\UseCase\Query\Session\GetSessionByAccessToken\GetSessionByAccessTokenHandler;
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\{SessionId, SessionMetadata};
use Shared\Domain\ValueObject\{IpAddress, UserAgent};

/**
 * Test SessionStatusServiceTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: SessionStatusService::class)]
final class SessionStatusServiceTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testIsAccessTokenRevokedIsTrueForARevokedSession(): void
  {
    $session = $this->createSession();
    $session->revoke();

    self::assertTrue($this->createService($session)->isAccessTokenRevoked('access-token-1'));
  }

  #[Test]
  public function testIsAccessTokenRevokedIsFalseForAnActiveSession(): void
  {
    self::assertFalse($this->createService($this->createSession())->isAccessTokenRevoked('access-token-1'));
  }

  #[Test]
  public function testIsAccessTokenRevokedIsFalseForAnUntrackedToken(): void
  {
    // Untracked must not read as revoked: recording is best-effort, so an
    // absent row would otherwise lock a user out for the token's lifetime.
    self::assertFalse($this->createService(null)->isAccessTokenRevoked('unknown'));
  }

  #[Test]
  public function testIsAccessTokenRevokedShortCircuitsOnAnEmptyIdentifier(): void
  {
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::never())->method('findByAccessTokenId');

    $service = new SessionStatusService(
      getSessionByAccessTokenHandler: new GetSessionByAccessTokenHandler(sessionRepository: $repository),
    );

    self::assertFalse($service->isAccessTokenRevoked(''));
  }
  // #endregion

  // #region Helpers
  private function createService(?Session $session): SessionStatusService
  {
    $repository = $this->createStub(SessionRepositoryPort::class);
    $repository->method('findByAccessTokenId')->willReturn($session);

    return new SessionStatusService(
      getSessionByAccessTokenHandler: new GetSessionByAccessTokenHandler(sessionRepository: $repository),
    );
  }

  private function createSession(): Session
  {
    return Session::create(
      id: new SessionId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      ipAddress: new IpAddress('192.168.1.1'),
      userAgent: new UserAgent('Mozilla/5.0'),
      metadata: new SessionMetadata(deviceType: 'desktop'),
      accessTokenId: 'access-token-1',
      refreshTokenId: 'refresh-token-1',
    );
  }
  // #endregion
}
