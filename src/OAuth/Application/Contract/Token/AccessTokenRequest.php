<?php

declare(strict_types=1);

namespace OAuth\Application\Contract\Token;

/** Credentials and grant parameters sent to the authorization server. */
final readonly class AccessTokenRequest
{
  public function __construct(
    public string $grantType,
    public string $clientId,
    public string $clientSecret,
    public AccessTokenGrantParameters $grant = new AccessTokenGrantParameters(),
  ) {
  }
}
