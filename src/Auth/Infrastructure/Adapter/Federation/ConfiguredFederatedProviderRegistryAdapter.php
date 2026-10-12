<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Federation;

use Auth\Application\Contract\Federation\{FederatedAuthorization, FederatedProfile, FederatedProviderDescriptor, ProviderId};
use Auth\Application\Port\Outbound\Federation\FederatedProviderClientPort;

use function array_merge;
use function in_array;

/**
 * Class ConfiguredFederatedProviderRegistryAdapter
 *
 * Registers legacy clients and generic OIDC configuration behind one application port.
 *
 * @category Adapter
 */
final readonly class ConfiguredFederatedProviderRegistryAdapter implements FederatedProviderClientPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param LeagueFederatedProviderAdapter $legacy existing Google and Microsoft clients
   * @param GenericOidcProviderAdapter $oidc server-configured generic OIDC clients
   *
   * @return void
   */
  public function __construct(private LeagueFederatedProviderAdapter $legacy, private GenericOidcProviderAdapter $oidc)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method descriptors
   *
   * @access public
   *
   * @return list<FederatedProviderDescriptor> safe configured provider metadata
   */
  public function descriptors(): array
  {
    return array_merge($this->legacy->descriptors(), $this->oidc->descriptors());
  }

  /**
   * Method isEnabled
   *
   * @access public
   *
   * @param ProviderId $provider requested provider namespace
   *
   * @return bool current server availability
   */
  public function isEnabled(ProviderId $provider): bool
  {
    return $this->client($provider)->isEnabled($provider);
  }

  /**
   * Method start
   *
   * @access public
   *
   * @param ProviderId $provider requested provider namespace
   * @param string $redirectUri server-derived callback URI
   * @param string $state browser-bound state
   *
   * @return FederatedAuthorization validated authorization parameters
   */
  public function start(ProviderId $provider, string $redirectUri, string $state): FederatedAuthorization
  {
    return $this->client($provider)->start($provider, $redirectUri, $state);
  }

  /**
   * Method complete
   *
   * @access public
   *
   * @param ProviderId $provider requested provider namespace
   * @param string $redirectUri retained callback URI
   * @param string $code provider authorization code
   * @param string $codeVerifier retained PKCE verifier
   * @param ?string $nonceHash retained generic nonce hash
   *
   * @return FederatedProfile authenticated provider identity
   */
  public function complete(ProviderId $provider, string $redirectUri, string $code, string $codeVerifier, ?string $nonceHash = null): FederatedProfile
  {
    return $this->client($provider)->complete($provider, $redirectUri, $code, $codeVerifier, $nonceHash);
  }

  /**
   * Method client
   *
   * @access private
   *
   * @param ProviderId $provider provider namespace
   *
   * @return FederatedProviderClientPort registered client
   */
  private function client(ProviderId $provider): FederatedProviderClientPort
  {
    return in_array($provider->value, ['google', 'microsoft'], true) ? $this->legacy : $this->oidc;
  }
  // #endregion
}
