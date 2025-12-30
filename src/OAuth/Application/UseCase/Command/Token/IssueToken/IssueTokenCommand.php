<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\Token\IssueToken;

use Shared\Application\Message\CommandMessage;

/**
 * Command IssueTokenCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IssueTokenCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * IssueTokenCommand class.
   *
   * @since 1.0.0
   *
   * @param string $grantType the grant type
   * @param string $clientId the client ID
   * @param string $clientSecret the client secret
   * @param string|null $scope the requested scope(s)
   * @param string|null $refreshToken the refresh token (for refresh_token grant)
   * @param string|null $code the authorization code (for authorization_code grant)
   * @param string|null $redirectUri the redirect URI (for authorization_code grant)
   * @param string|null $codeVerifier the PKCE code verifier
   * @param string|null $ipAddress the client IP address
   */
  public function __construct(
    public readonly string $grantType,
    public readonly string $clientId,
    public readonly string $clientSecret,
    public readonly ?string $scope = null,
    public readonly ?string $refreshToken = null,
    public readonly ?string $code = null,
    public readonly ?string $redirectUri = null,
    public readonly ?string $codeVerifier = null,
    public readonly ?string $ipAddress = null,
  ) {
  }
  // #endregion
}
