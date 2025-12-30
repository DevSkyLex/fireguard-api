<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\League\Server;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use OAuth\Application\Port\Outbound\Token\AuthorizationServerPort;
use OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenResult;
use OAuth\Domain\Exception\Token\AuthorizationException;
use Throwable;

use function array_filter;
use function json_decode;

/**
 * Server AuthorizationServerAdapter.
 *
 * @category Server
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthorizationServerAdapter implements AuthorizationServerPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the AuthorizationServerAdapter.
   *
   * @since 1.0.0
   *
   * @param AuthorizationServer $authorizationServer the League authorization server
   */
  public function __construct(
    private AuthorizationServer $authorizationServer,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method issueAccessToken
   * {@inheritDoc}
   *
   * Issue an access token via the League authorization server.
   *
   * @since 1.0.0
   *
   * @param string $grantType the grant type
   * @param string $clientId the client ID
   * @param string $clientSecret the client secret
   * @param string|null $scope the scopes
   * @param string|null $refreshToken the refresh token
   * @param string|null $code the authorization code
   * @param string|null $redirectUri the redirect URI
   * @param string|null $codeVerifier the PKCE code verifier
   *
   * @return IssueTokenResult the issued token result
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
    ], fn ($value) => null !== $value);

    $request = new ServerRequest(method: 'POST', uri: '/token')
      ->withParsedBody(data: $parsedBody);

    $response = new Response();

    try {
      $response = $this->authorizationServer->respondToAccessTokenRequest(
        request: $request,
        response: $response,
      );

      /** @var array{access_token?: string, token_type?: string, expires_in?: int, refresh_token?: string, scope?: string} $body */
      $body = json_decode((string) $response->getBody(), true) ?? [];

      return new IssueTokenResult(
        accessToken: $body['access_token'] ?? '',
        tokenType: $body['token_type'] ?? 'Bearer',
        expiresIn: $body['expires_in'] ?? 0,
        refreshToken: $body['refresh_token'] ?? null,
        scope: $body['scope'] ?? null,
      );
    } catch (OAuthServerException $exception) {
      if ('server_error' === $exception->getErrorType()) {
        if ('authorization_code' === $grantType) {
          throw AuthorizationException::invalidGrant('Invalid authorization code.', $exception);
        }

        if ('refresh_token' === $grantType) {
          throw AuthorizationException::invalidGrant('Invalid refresh token.', $exception);
        }
      }

      throw match ($exception->getErrorType()) {
        'invalid_request' => AuthorizationException::invalidRequest($exception->getMessage()),
        'invalid_client' => AuthorizationException::invalidClient($exception->getMessage()),
        'invalid_grant' => AuthorizationException::invalidGrant($exception->getMessage()),
        'invalid_scope' => AuthorizationException::invalidScope($exception->getMessage()),
        'unauthorized_client' => AuthorizationException::unauthorizedClient($exception->getMessage()),
        'unsupported_grant_type' => AuthorizationException::unsupportedGrantType($exception->getMessage()),
        'access_denied' => AuthorizationException::accessDenied($exception->getMessage()),
        'temporarily_unavailable' => AuthorizationException::temporarilyUnavailable($exception->getMessage()),
        'server_error' => AuthorizationException::serverError($exception->getMessage()),
        default => AuthorizationException::serverError($exception->getMessage()),
      };
    } catch (Throwable $exception) {
      if ('authorization_code' === $grantType) {
        throw AuthorizationException::invalidGrant('Invalid authorization code.', $exception);
      }

      if ('refresh_token' === $grantType) {
        throw AuthorizationException::invalidGrant('Invalid refresh token.', $exception);
      }

      throw AuthorizationException::serverError('Authorization server error.', $exception);
    }
  }
  // #endregion
}
