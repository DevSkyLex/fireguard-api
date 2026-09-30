<?php

declare(strict_types=1);

namespace OAuth\Application\Contract\Token;

/** Credentials and grant parameters sent to the authorization server. */
final readonly class AccessTokenRequest
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries the OAuth client credentials and fields required by a token grant.
   *
   * @access public
   *
   * @param string $grantType oAuth grant type requested from the authorization server
   * @param string $clientId registered OAuth client identifier
   * @param string $clientSecret client credential used to authenticate the client
   * @param AccessTokenGrantParameters $grant optional grant-specific protocol parameters
   *
   * @return void
   */
  public function __construct(
    public string $grantType,
    public string $clientId,
    public string $clientSecret,
    public AccessTokenGrantParameters $grant = new AccessTokenGrantParameters(),
  ) {
  }
  // #endregion
}
