<?php

declare(strict_types=1);

namespace Auth\Application\Port\Inbound\Federation;

use Auth\Application\Contract\Federation\{FederatedConnections, FederatedLogin, FederatedLoginContext, FederatedProviderDescriptor, ProviderId};

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
   * @return list<FederatedProviderDescriptor> enabled provider identities
   */
  public function enabledProviders(): array;

  public function startLogin(ProviderId $provider, string $returnUrl, ?string $browserBinding = null): string;

  public function startLink(ProviderId $provider, string $userId, ?string $browserBinding = null): string;

  public function completeLogin(ProviderId $provider, string $state, string $code, string $browserBinding, ?string $providerError, FederatedLoginContext $loginContext): FederatedLogin;

  public function completeLink(ProviderId $provider, string $state, string $code, string $userId, string $browserBinding, ?string $providerError): FederatedConnections;

  public function connections(string $userId): FederatedConnections;

  public function remove(ProviderId $provider, string $userId): FederatedConnections;
}
