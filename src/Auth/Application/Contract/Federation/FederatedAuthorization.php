<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

/**
 * Contract FederatedAuthorization.
 *
 * Provider authorization URL together with the generated PKCE verifier.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedAuthorization
{
  public function __construct(
    public string $authorizationUrl,
    public string $codeVerifier,
  ) {
  }
}
