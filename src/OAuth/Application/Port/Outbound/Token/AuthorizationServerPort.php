<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound\Token;

use OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenResult;
use OAuth\Domain\Exception\Token\AuthorizationException;

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
   * @param AccessTokenRequest $tokenRequest the client credentials and grant parameters
   *
   * @throws AuthorizationException if token issuance fails
   *
   * @return IssueTokenResult the token result
   */
  public function issueAccessToken(AccessTokenRequest $tokenRequest): IssueTokenResult;
  // #endregion
}
