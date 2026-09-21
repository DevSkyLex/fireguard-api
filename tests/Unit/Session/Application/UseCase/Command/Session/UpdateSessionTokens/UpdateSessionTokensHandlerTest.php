<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Application\UseCase\Command\Session\UpdateSessionTokens;

use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\UseCase\Command\Session\UpdateSessionTokens\{UpdateSessionTokensCommand, UpdateSessionTokensHandler};

final class UpdateSessionTokensHandlerTest extends TestCase
{
  public function testOnlyAnAtomicRotationCanAuthorizeTheNextPair(): void
  {
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())->method('rotateTokens')->with('refresh-old', 'access-old', 'access-new', 'refresh-new')->willReturn(true);
    $result = new UpdateSessionTokensHandler($repository)(new UpdateSessionTokensCommand('refresh-old', 'access-old', 'access-new', 'refresh-new'));
    self::assertTrue($result->updated);
  }

  public function testUsedRefreshTokenCannotFallBackToTheAccessToken(): void
  {
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())->method('rotateTokens')->willReturn(false);
    $repository->expects(self::never())->method('findByAccessTokenId');
    $result = new UpdateSessionTokensHandler($repository)(new UpdateSessionTokensCommand('refresh-used', 'access-old', 'access-new', 'refresh-new'));
    self::assertFalse($result->updated);
  }

  public function testMissingPairCannotRotate(): void
  {
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::never())->method('rotateTokens');
    $result = new UpdateSessionTokensHandler($repository)(new UpdateSessionTokensCommand('refresh-old', null, 'access-new', 'refresh-new'));
    self::assertFalse($result->updated);
  }
}
