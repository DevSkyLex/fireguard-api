<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

use OAuth\Application\UseCase\Command\IssueToken\IssueTokenResult;
use Auth\Domain\Exception\AuthorizationException;

/**
 * Interface AuthorizationServerPort
 *
 * Port for OAuth2 Authorization Server operations.
 * This abstracts the underlying OAuth2 server implementation
 * (e.g., League OAuth2 Server) from the Application layer.
 *
 * @category Port
 * @package Auth\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AuthorizationServerPort
{
  //#region Methods
  /**
   * Method issueAccessToken
   *
   * Issues an access token for the given 
   * credentials.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $grantType The grant type.
   * @param string $clientId The client ID.
   * @param string $clientSecret The client secret.
   * @param string|null $scope The requested scope.
   * @param string|null $refreshToken The refresh token (for refresh_token grant).
   * @param string|null $code The authorization code (for authorization_code grant).
   * @param string|null $redirectUri The redirect URI (for authorization_code grant).
   * @param string|null $codeVerifier The PKCE code verifier.
   *
   * @return IssueTokenResult The token result.
   *
   * @throws AuthorizationException If token issuance fails.
   */
  public function issueAccessToken(
    string $grantType,
    string $clientId,
    string $clientSecret,
    ?string $scope = null,
    ?string $refreshToken = null,
    ?string $code = null,
    ?string $redirectUri = null,
    ?string $codeVerifier = null
  ): IssueTokenResult;
  //#endregion
}
