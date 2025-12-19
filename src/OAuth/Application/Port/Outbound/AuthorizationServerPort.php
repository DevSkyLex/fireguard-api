<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

use Auth\Domain\Exception\AuthorizationException;
use OAuth\Application\UseCase\Command\IssueToken\IssueTokenResult;

/**
 * Interface AuthorizationServerPort.
 *
 * Port for OAuth2 Authorization Server operations.
 * This abstracts the underlying OAuth2 server implementation
 * (e.g., League OAuth2 Server) from the Application layer.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AuthorizationServerPort
{
    // #region Methods
    /**
     * Method issueAccessToken.
     *
     * Issues an access token for the given
     * credentials.
     *
     * @since 1.0.0
     *
     * @param string      $grantType    the grant type
     * @param string      $clientId     the client ID
     * @param string      $clientSecret the client secret
     * @param string|null $scope        the requested scope
     * @param string|null $refreshToken the refresh token (for refresh_token grant)
     * @param string|null $code         the authorization code (for authorization_code grant)
     * @param string|null $redirectUri  the redirect URI (for authorization_code grant)
     * @param string|null $codeVerifier the PKCE code verifier
     *
     * @return IssueTokenResult the token result
     *
     * @throws AuthorizationException if token issuance fails
     */
    public function issueAccessToken(
        string $grantType,
        string $clientId,
        string $clientSecret,
        ?string $scope = null,
        ?string $refreshToken = null,
        ?string $code = null,
        ?string $redirectUri = null,
        ?string $codeVerifier = null,
    ): IssueTokenResult;
    // #endregion
}
