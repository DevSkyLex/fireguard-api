<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\IssueToken;

use Shared\Application\Message\CommandMessage;

/**
 * Command IssueTokenCommand
 * @final
 *
 * Command to issue an OAuth2 access token.
 *
 * @category Command
 * @package Auth\Application\UseCase\Command\IssueToken
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IssueTokenCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * IssueTokenCommand class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $grantType The grant type.
   * @param string $clientId The client ID.
   * @param string $clientSecret The client secret.
   * @param string|null $scope The requested scope(s).
   * @param string|null $refreshToken The refresh token (for refresh_token grant).
   * @param string|null $code The authorization code (for authorization_code grant).
   * @param string|null $redirectUri The redirect URI (for authorization_code grant).
   * @param string|null $codeVerifier The PKCE code verifier.
   */
  public function __construct(
    public readonly string $grantType,
    public readonly string $clientId,
    public readonly string $clientSecret,
    public readonly ?string $scope = null,
    public readonly ?string $refreshToken = null,
    public readonly ?string $code = null,
    public readonly ?string $redirectUri = null,
    public readonly ?string $codeVerifier = null
  ) {}
  //#endregion
}
