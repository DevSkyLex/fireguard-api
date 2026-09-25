<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

/**
 * Client context passed to the session issuer after a federated callback.
 */
final readonly class FederatedLoginContext
{
  public function __construct(
    public ?string $ipAddress,
    public ?string $userAgent,
    public ?string $trustedDeviceToken,
  ) {
  }
}
