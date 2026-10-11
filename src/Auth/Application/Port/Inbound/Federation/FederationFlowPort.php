<?php

declare(strict_types=1);

namespace Auth\Application\Port\Inbound\Federation;

use Auth\Application\Contract\Federation\{FederatedConnections, FederatedLogin, FederatedLoginContext, FederatedProvider};

/**
 * FederationFlowPort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FederationFlowPort
{
  /**
   * @return list<FederatedProvider> enabled provider identities
   */
  public function enabledProviders(): array;

  public function startLogin(FederatedProvider $provider, string $returnUrl, ?string $browserBinding = null): string;

  public function startLink(FederatedProvider $provider, string $userId, ?string $browserBinding = null): string;

  public function completeLogin(FederatedProvider $provider, string $state, string $code, string $browserBinding, ?string $providerError, FederatedLoginContext $loginContext): FederatedLogin;

  public function completeLink(FederatedProvider $provider, string $state, string $code, string $userId, string $browserBinding, ?string $providerError): FederatedConnections;

  public function connections(string $userId): FederatedConnections;

  public function remove(FederatedProvider $provider, string $userId): FederatedConnections;
}
