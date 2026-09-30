<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

/**
 * Contract FederatedAuthorization.
 *
 * Provider authorization URL together with the generated PKCE verifier.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedAuthorization
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the provider authorization URL and PKCE verifier for the authorization-code exchange.
   *
   * @access public
   *
   * @param string $authorizationUrl provider URL to which the browser is redirected
   * @param string $codeVerifier PKCE verifier retained for the token exchange
   *
   * @return void
   */
  public function __construct(
    public string $authorizationUrl,
    public string $codeVerifier,
  ) {
  }
  // #endregion
}
