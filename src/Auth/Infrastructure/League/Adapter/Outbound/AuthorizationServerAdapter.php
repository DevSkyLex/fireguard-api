<?php

declare(strict_types=1);

namespace Auth\Infrastructure\League\Adapter\Outbound;

use Auth\Application\Port\Outbound\AuthorizationServerPort;
use Auth\Application\UseCase\Command\IssueToken\IssueTokenResult;
use Auth\Domain\Exception\AuthorizationException;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

/**
 * Adapter AuthorizationServerAdapter
 * @final
 *
 * Adapter for League OAuth2 Authorization Server.
 * Implements the AuthorizationServerPort to abstract the
 * League OAuth2 Server from the Application layer.
 *
 * @category Adapter
 * @package Auth\Infrastructure\League\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthorizationServerAdapter implements AuthorizationServerPort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the AuthorizationServerAdapter class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AuthorizationServer $authorizationServer The League OAuth2 authorization server.
   */
  public function __construct(
    private AuthorizationServer $authorizationServer
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method issueAccessToken
   * {@inheritDoc}
   *
   * @access public
   * @since 1.0.0
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
  ): IssueTokenResult {
    $parsedBody = array_filter([
      'grant_type' => $grantType,
      'client_id' => $clientId,
      'client_secret' => $clientSecret,
      'scope' => $scope,
      'refresh_token' => $refreshToken,
      'code' => $code,
      'redirect_uri' => $redirectUri,
      'code_verifier' => $codeVerifier,
    ], fn($value) => $value !== null);

    $request = (new ServerRequest('POST', '/token'))
      ->withParsedBody($parsedBody);

    $response = new Response();

    try {
      $response = $this->authorizationServer->respondToAccessTokenRequest($request, $response);

      /** @var array{access_token?: string, token_type?: string, expires_in?: int, refresh_token?: string, scope?: string} $body */
      $body = json_decode((string) $response->getBody(), true) ?? [];

      return new IssueTokenResult(
        accessToken: $body['access_token'] ?? '',
        tokenType: $body['token_type'] ?? 'Bearer',
        expiresIn: $body['expires_in'] ?? 0,
        refreshToken: $body['refresh_token'] ?? null,
        scope: $body['scope'] ?? null
      );
    } catch (OAuthServerException $exception) {
      throw match ($exception->getErrorType()) {
        'invalid_client' => AuthorizationException::invalidClient(),
        'invalid_grant' => AuthorizationException::invalidGrant($exception->getMessage()),
        'invalid_scope' => AuthorizationException::invalidScope(),
        default => AuthorizationException::serverError($exception->getMessage()),
      };
    }
  }
  //#endregion
}
