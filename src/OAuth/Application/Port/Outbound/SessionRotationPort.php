<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

/**
 * Interface SessionRotationPort
 *
 * Rotates an interactive refresh and access token pair as one session operation.
 *
 * @category Port
 */
interface SessionRotationPort
{
  /**
   * A successful rotation consumes the previous pair exactly once.
   */
  public function rotate(string $refreshTokenId, string $accessTokenId, string $newAccessTokenId, string $newRefreshTokenId): bool;
}
