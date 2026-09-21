<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Session;

use OAuth\Application\Port\Outbound\SessionRotationPort;
use Session\Application\Port\Inbound\Tracking\SessionTrackingPort;

final readonly class SessionRotationAdapter implements SessionRotationPort
{
  public function __construct(private SessionTrackingPort $sessions)
  {
  }

  public function rotate(string $refreshTokenId, string $accessTokenId, string $newAccessTokenId, string $newRefreshTokenId): bool
  {
    return $this->sessions->rotateSessionTokens($refreshTokenId, $accessTokenId, $newAccessTokenId, $newRefreshTokenId);
  }
}
