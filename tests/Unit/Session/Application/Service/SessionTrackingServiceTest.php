<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Application\Service;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\Service\SessionTrackingService;
use Session\Application\UseCase\Command\Session\CreateSession\CreateSessionHandler;
use Session\Application\UseCase\Command\Session\RevokeSessionByToken\RevokeSessionByTokenHandler;
use Session\Application\UseCase\Command\Session\UpdateSessionTokens\UpdateSessionTokensHandler;
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\SessionId;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Domain\ValueObject\{IpAddress, UserAgent};

/**
 * Test SessionTrackingServiceTest.
 *
 * @category Service Tests
 */
#[CoversClass(className: SessionTrackingService::class)]
final class SessionTrackingServiceTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testRecordSessionSkipsEmptyUserId(): void
  {
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::never())
      ->method('save');

    $service = new SessionTrackingService(
      createSessionHandler: new CreateSessionHandler(
        sessionRepository: $repository,
        uuidFactory: $this->createUuidFactory('123e4567-e89b-12d3-a456-426614174000'),
      ),
      updateSessionTokensHandler: new UpdateSessionTokensHandler(
        sessionRepository: $this->createMock(SessionRepositoryPort::class),
      ),
      revokeSessionByTokenHandler: new RevokeSessionByTokenHandler(
        sessionRepository: $this->createMock(SessionRepositoryPort::class),
      ),
    );

    $service->recordSession('', '127.0.0.1', 'agent', null, null, false);
  }

  #[Test]
  public function testRecordSessionDispatchesCreate(): void
  {
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(function (Session $session): bool {
        return 'user-1' === $session->userId()
          && 'access-1' === $session->accessTokenId()
          && 'refresh-1' === $session->refreshTokenId();
      }));

    $service = new SessionTrackingService(
      createSessionHandler: new CreateSessionHandler(
        sessionRepository: $repository,
        uuidFactory: $this->createUuidFactory('123e4567-e89b-12d3-a456-426614174000'),
      ),
      updateSessionTokensHandler: new UpdateSessionTokensHandler(
        sessionRepository: $this->createMock(SessionRepositoryPort::class),
      ),
      revokeSessionByTokenHandler: new RevokeSessionByTokenHandler(
        sessionRepository: $this->createMock(SessionRepositoryPort::class),
      ),
    );

    $service->recordSession('user-1', '127.0.0.1', 'agent', 'access-1', 'refresh-1', true);
  }

  #[Test]
  public function testRotateSessionTokensDispatchesUpdate(): void
  {
    $session = $this->createSession();

    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByRefreshTokenId')
      ->with('refresh-old')
      ->willReturn($session);
    $repository->expects(self::once())
      ->method('save')
      ->with($session);

    $service = new SessionTrackingService(
      createSessionHandler: new CreateSessionHandler(
        sessionRepository: $this->createMock(SessionRepositoryPort::class),
        uuidFactory: $this->createUuidFactory('123e4567-e89b-12d3-a456-426614174000'),
      ),
      updateSessionTokensHandler: new UpdateSessionTokensHandler(
        sessionRepository: $repository,
      ),
      revokeSessionByTokenHandler: new RevokeSessionByTokenHandler(
        sessionRepository: $this->createMock(SessionRepositoryPort::class),
      ),
    );

    $service->rotateSessionTokens('refresh-old', 'access-old', 'access-new', 'refresh-new');
  }

  #[Test]
  public function testRotateSessionTokensSkipsWhenMissingIds(): void
  {
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::never())
      ->method('findByRefreshTokenId');

    $service = new SessionTrackingService(
      createSessionHandler: new CreateSessionHandler(
        sessionRepository: $this->createMock(SessionRepositoryPort::class),
        uuidFactory: $this->createUuidFactory('123e4567-e89b-12d3-a456-426614174000'),
      ),
      updateSessionTokensHandler: new UpdateSessionTokensHandler(
        sessionRepository: $repository,
      ),
      revokeSessionByTokenHandler: new RevokeSessionByTokenHandler(
        sessionRepository: $this->createMock(SessionRepositoryPort::class),
      ),
    );

    $service->rotateSessionTokens('', null, 'access-new', 'refresh-new');
  }

  #[Test]
  public function testRevokeSessionByTokenSkipsEmptyTokens(): void
  {
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::never())
      ->method('findByRefreshTokenId');
    $repository->expects(self::never())
      ->method('findByAccessTokenId');

    $service = new SessionTrackingService(
      createSessionHandler: new CreateSessionHandler(
        sessionRepository: $this->createMock(SessionRepositoryPort::class),
        uuidFactory: $this->createUuidFactory('123e4567-e89b-12d3-a456-426614174000'),
      ),
      updateSessionTokensHandler: new UpdateSessionTokensHandler(
        sessionRepository: $this->createMock(SessionRepositoryPort::class),
      ),
      revokeSessionByTokenHandler: new RevokeSessionByTokenHandler(
        sessionRepository: $repository,
      ),
    );

    $service->revokeSessionByToken(null, '');
  }

  #[Test]
  public function testRevokeSessionByTokenDispatchesCommand(): void
  {
    $session = $this->createSession();

    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByAccessTokenId')
      ->with('access-1')
      ->willReturn($session);
    $repository->expects(self::once())
      ->method('save')
      ->with($session);

    $service = new SessionTrackingService(
      createSessionHandler: new CreateSessionHandler(
        sessionRepository: $this->createMock(SessionRepositoryPort::class),
        uuidFactory: $this->createUuidFactory('123e4567-e89b-12d3-a456-426614174000'),
      ),
      updateSessionTokensHandler: new UpdateSessionTokensHandler(
        sessionRepository: $this->createMock(SessionRepositoryPort::class),
      ),
      revokeSessionByTokenHandler: new RevokeSessionByTokenHandler(
        sessionRepository: $repository,
      ),
    );

    $service->revokeSessionByToken(null, 'access-1');
  }

  private function createUuidFactory(string $uuid): UuidFactory
  {
    $generator = new class ($uuid) implements UuidGeneratorPort {
      public function __construct(private string $uuid)
      {
      }

      public function generate(): string
      {
        return $this->uuid;
      }
    };

    return new UuidFactory(generator: $generator);
  }

  private function createSession(): Session
  {
    return Session::create(
      id: new SessionId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-1',
      ipAddress: new IpAddress('127.0.0.1'),
      userAgent: new UserAgent('agent'),
      accessTokenId: 'access-1',
      refreshTokenId: 'refresh-1',
    );
  }
  // #endregion
}
