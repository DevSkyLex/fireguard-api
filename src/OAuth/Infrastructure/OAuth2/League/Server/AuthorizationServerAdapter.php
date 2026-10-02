<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\League\Server;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\{Parser, Plain};
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\{Response, ServerRequest};
use OAuth\Application\Contract\Token\AccessTokenRequest;
use OAuth\Application\Port\Outbound\Token\{AuthorizationServerPort, GrantLifecyclePort};
use OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenResult;
use OAuth\Domain\Exception\Token\AuthorizationException;
use Throwable;

use function array_filter;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function strlen;

/**
 * Server AuthorizationServerAdapter.
 *
 * @category Server
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
    private GrantLifecyclePort $grantLifecycle,
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
   * @param AccessTokenRequest $tokenRequest the client credentials and grant parameters
   *
   * @return IssueTokenResult the issued token result
   */
  public function issueAccessToken(AccessTokenRequest $tokenRequest): IssueTokenResult
  {
    return $this->grantLifecycle->transactional(fn (): IssueTokenResult => $this->issueWithinTransaction($tokenRequest));
  }

  /**
   * Method issueWithinTransaction
   *
   * Keeps validation, grant consumption and both token writes inside the user-lock lifetime.
   *
   * @access private
   *
   * @param AccessTokenRequest $tokenRequest the validated token input
   *
   * @return IssueTokenResult the token response after persistence
   */
  private function issueWithinTransaction(AccessTokenRequest $tokenRequest): IssueTokenResult
  {
    $grantType = $tokenRequest->grantType;
    $parsedBody = array_filter([
      'grant_type' => $grantType,
      'client_id' => $tokenRequest->clientId,
      'client_secret' => $tokenRequest->clientSecret,
      'scope' => $tokenRequest->grant->scope,
      'refresh_token' => $tokenRequest->grant->refreshToken,
      'code' => $tokenRequest->grant->code,
      'redirect_uri' => $tokenRequest->grant->redirectUri,
      'code_verifier' => $tokenRequest->grant->codeVerifier,
    ], fn ($value) => null !== $value);

    $request = new ServerRequest(method: 'POST', uri: '/token')
      ->withParsedBody(data: $parsedBody);

    $response = new Response();

    try {
      $response = $this->authorizationServer->respondToAccessTokenRequest(
        request: $request,
        response: $response,
      );

      return $this->parseIssuedTokenResponse((string) $response->getBody());
    } catch (OAuthServerException $exception) {
      throw $this->translateOAuthServerException($exception, $grantType);
    } catch (Throwable $exception) {
      throw $this->translateUnexpectedException($exception, $grantType);
    }
  }

  /**
   * Method parseIssuedTokenResponse
   *
   * Validates the server-issued JWT identifier and authoritative scopes before exposing a token result.
   *
   * @access private
   *
   * @param string $responseBody the League token response body
   *
   * @return IssueTokenResult the validated token response
   */
  private function parseIssuedTokenResponse(string $responseBody): IssueTokenResult
  {
    /** @var array{access_token?: string, token_type?: string, expires_in?: int, refresh_token?: string, scope?: string} $body */
    $body = json_decode($responseBody, true) ?? [];

    $accessToken = $body['access_token'] ?? '';
    if ('' === $accessToken) {
      throw AuthorizationException::serverError('Authorization server returned no access token.');
    }
    $parsedToken = new Parser(new JoseEncoder())->parse($accessToken);
    if (!$parsedToken instanceof Plain) {
      throw AuthorizationException::serverError('Issued access token has an unusable format.');
    }
    $tokenId = $parsedToken->claims()->get('jti', null);
    if (!is_string($tokenId) || '' === $tokenId || strlen($tokenId) > 100) {
      throw AuthorizationException::serverError('Issued access token has no usable identifier.');
    }

    $issuedScopes = $parsedToken->claims()->get('scopes', []);
    if (!is_array($issuedScopes)) {
      throw AuthorizationException::serverError('Issued access token has unusable scopes.');
    }
    $scopeIdentifiers = [];
    foreach ($issuedScopes as $scopeIdentifier) {
      if (!is_string($scopeIdentifier) || '' === $scopeIdentifier) {
        throw AuthorizationException::serverError('Issued access token has unusable scopes.');
      }
      $scopeIdentifiers[] = $scopeIdentifier;
    }

    return new IssueTokenResult(
      accessToken: $accessToken,
      tokenType: $body['token_type'] ?? 'Bearer',
      expiresIn: $body['expires_in'] ?? 0,
      tokenId: $tokenId,
      refreshToken: $body['refresh_token'] ?? null,
      scope: implode(' ', $scopeIdentifiers),
    );
  }

  /**
   * Method translateOAuthServerException
   *
   * Preserves League error types while hiding storage failures for user-bound code and refresh grants.
   *
   * @access private
   *
   * @param OAuthServerException $exception the failed League operation
   * @param string $grantType the requested grant family
   *
   * @return AuthorizationException the domain error exposed by the token boundary
   */
  private function translateOAuthServerException(OAuthServerException $exception, string $grantType): AuthorizationException
  {
    if ('server_error' === $exception->getErrorType()) {
      if ('authorization_code' === $grantType) {
        return AuthorizationException::invalidGrant('Invalid authorization code.', $exception);
      }

      if ('refresh_token' === $grantType) {
        return AuthorizationException::invalidGrant('Invalid refresh token.', $exception);
      }
    }

    return match ($exception->getErrorType()) {
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
  }

  /**
   * Method translateUnexpectedException
   *
   * Keeps unexpected code and refresh failures opaque while retaining the original cause for diagnostics.
   *
   * @access private
   *
   * @param Throwable $exception the unexpected failure during token issuance
   * @param string $grantType the requested grant family
   *
   * @return AuthorizationException the grant-specific domain error
   */
  private function translateUnexpectedException(Throwable $exception, string $grantType): AuthorizationException
  {
    return match ($grantType) {
      'authorization_code' => AuthorizationException::invalidGrant('Invalid authorization code.', $exception),
      'refresh_token' => AuthorizationException::invalidGrant('Invalid refresh token.', $exception),
      default => AuthorizationException::serverError('Authorization server error.', $exception),
    };
  }
  // #endregion
}
