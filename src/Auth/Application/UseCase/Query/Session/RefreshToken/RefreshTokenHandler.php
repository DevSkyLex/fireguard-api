<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\Session\RefreshToken;

use Auth\Application\Port\Outbound\TokenRefreshPort;
use Shared\Application\Message\QueryHandler;

/** The issuer must consume the previous session pair before returning tokens. */
final readonly class RefreshTokenHandler implements QueryHandler
{
  public function __construct(private TokenRefreshPort $tokenRefresh)
  {
  }

  public function __invoke(RefreshTokenQuery $query): RefreshTokenResult
  {
    return $this->tokenRefresh->refresh($query->refreshToken, $query->ipAddress);
  }
}
