<?php

declare(strict_types=1);

namespace OAuth\Application\Contract\Client;

/**
 * Publishes fresh lifecycle facts without exposing a persisted secret or record.
 *
 * @category Contract
 */
final readonly class ClientLifecycleProof
{
  public function __construct(public string $clientId, public int $securityRevision, public bool $active, public bool $deleted, public string $secretFingerprint)
  {
  }
}
