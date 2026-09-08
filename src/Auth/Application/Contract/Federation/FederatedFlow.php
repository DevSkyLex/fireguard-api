<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

use Auth\Domain\ValueObject\Federation\FederatedProvider;
use DateTimeImmutable;

/**
 * Contract FederatedFlow.
 *
 * One-time server-side state for a federated authorization-code flow.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedFlow
{
  public function __construct(
    public string $stateHash,
    public string $browserBindingHash,
    public FederatedProvider $provider,
    public string $intent,
    public ?string $userId,
    public string $codeVerifier,
    public string $redirectUri,
    public string $returnUrl,
    public DateTimeImmutable $expiresAt,
  ) {
  }
}
