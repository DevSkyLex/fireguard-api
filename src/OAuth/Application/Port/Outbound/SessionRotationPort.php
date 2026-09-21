<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

interface SessionRotationPort
{
  /**
   * A successful rotation consumes the previous pair exactly once.
   */
  public function rotate(string $refreshTokenId, string $accessTokenId, string $newAccessTokenId, string $newRefreshTokenId): bool;
}
