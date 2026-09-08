<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound\Federation;

use Auth\Application\Contract\Federation\{FederatedAuthorization, FederatedProfile};
use Auth\Domain\ValueObject\Federation\FederatedProvider;

/**
 * Interface FederatedProviderClientPort.
 *
 * Exchanges data with configured Google and Microsoft OAuth clients.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FederatedProviderClientPort
{
  public function isEnabled(FederatedProvider $provider): bool;

  public function start(
    FederatedProvider $provider,
    string $redirectUri,
    string $state,
  ): FederatedAuthorization;

  public function complete(
    FederatedProvider $provider,
    string $redirectUri,
    string $code,
    string $codeVerifier,
  ): FederatedProfile;
}
