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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FederatedProviderClientPort
{
  // #region Methods
  /**
   * Method isEnabled
   *
   * Reports whether enabled the requested condition.
   *
   * @access public
   *
   * @param FederatedProvider $provider the provider
   *
   * @return bool
   */
  public function isEnabled(FederatedProvider $provider): bool;

  /**
   * Method start
   *
   * Starts provider authorization and returns the redirect details needed to continue the flow.
   *
   * @access public
   *
   * @param FederatedProvider $provider the provider
   * @param string $redirectUri the redirect uri
   * @param string $state the state
   *
   * @return FederatedAuthorization
   */
  public function start(
    FederatedProvider $provider,
    string $redirectUri,
    string $state,
  ): FederatedAuthorization;

  /**
   * Method complete
   *
   * Exchanges the provider response and verifier for the authenticated federated profile.
   *
   * @access public
   *
   * @param FederatedProvider $provider the provider
   * @param string $redirectUri the redirect uri
   * @param string $code the submitted code
   * @param string $codeVerifier the code verifier
   *
   * @return FederatedProfile
   */
  public function complete(
    FederatedProvider $provider,
    string $redirectUri,
    string $code,
    string $codeVerifier,
  ): FederatedProfile;
  // #endregion
}
