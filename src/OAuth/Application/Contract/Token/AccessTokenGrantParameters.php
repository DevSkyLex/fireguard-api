<?php

declare(strict_types=1);

namespace OAuth\Application\Contract\Token;

/** Optional parameters required by individual OAuth token grants. */
final readonly class AccessTokenGrantParameters
{
  public function __construct(
    public ?string $scope = null,
    public ?string $refreshToken = null,
    public ?string $code = null,
    public ?string $redirectUri = null,
    public ?string $codeVerifier = null,
  ) {
  }
}
