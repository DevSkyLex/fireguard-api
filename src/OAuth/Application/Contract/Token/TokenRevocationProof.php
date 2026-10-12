<?php

declare(strict_types=1);

namespace OAuth\Application\Contract\Token;

/**
 * Publishes cryptographically established native revocation identity and fresh owner epochs.
 *
 * @category Contract
 */
final readonly class TokenRevocationProof
{
  public function __construct(public string $kind, public ?string $tokenId = null, public ?string $clientId = null, public ?string $userId = null, public ?int $clientRevision = null, public ?int $userRevision = null, public bool $revoked = false)
  {
  }
}
