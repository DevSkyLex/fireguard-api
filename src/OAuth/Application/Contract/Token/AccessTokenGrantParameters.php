<?php

declare(strict_types=1);

namespace OAuth\Application\Contract\Token;

/** Optional parameters required by individual OAuth token grants. */
final readonly class AccessTokenGrantParameters
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries optional protocol fields used by supported OAuth token grants.
   *
   * @access public
   *
   * @param ?string $scope requested OAuth scopes
   * @param ?string $refreshToken refresh token presented for the refresh-token grant
   * @param ?string $code authorization code presented for the authorization-code grant
   * @param ?string $redirectUri redirect URI bound to the authorization request
   * @param ?string $codeVerifier pKCE verifier matching the authorization challenge
   *
   * @return void
   */
  public function __construct(
    public ?string $scope = null,
    public ?string $refreshToken = null,
    public ?string $code = null,
    public ?string $redirectUri = null,
    public ?string $codeVerifier = null,
  ) {
  }
  // #endregion
}
