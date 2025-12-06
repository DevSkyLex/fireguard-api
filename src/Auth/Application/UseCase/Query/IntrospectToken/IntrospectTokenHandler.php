<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\IntrospectToken;

use Auth\Application\Port\Inbound\IntrospectTokenUseCasePort;
use Auth\Application\Port\Outbound\AccessTokenRepositoryPort;
use Auth\Application\Port\Outbound\JwtParserPort;
use Auth\Application\Port\Outbound\RefreshTokenRepositoryPort;
use Auth\Application\Port\Outbound\TokenCachePort;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Handler IntrospectTokenHandler
 * @final
 *
 * Handles token introspection (RFC 7662).
 * Uses caching for improved performance.
 *
 * @category Handler
 * @package Auth\Application\UseCase\Query\IntrospectToken
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IntrospectTokenHandler implements IntrospectTokenUseCasePort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * IntrospectTokenHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param JwtParserPort $jwtParser The JWT parser.
   * @param AccessTokenRepositoryPort $accessTokenRepository The access token repository.
   * @param RefreshTokenRepositoryPort $refreshTokenRepository The refresh token repository.
   * @param TokenCachePort $tokenCache The token cache.
   * @param string $issuer The token issuer.
   */
  public function __construct(
    private JwtParserPort $jwtParser,
    private AccessTokenRepositoryPort $accessTokenRepository,
    private RefreshTokenRepositoryPort $refreshTokenRepository,
    private TokenCachePort $tokenCache,
    #[Autowire('%env(OAUTH_ISSUER)%')]
    private string $issuer = '',
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the IntrospectTokenQuery.
   *
   * @access public
   * @since 1.0.0
   *
   * @param IntrospectTokenQuery $query The query.
   *
   * @return IntrospectTokenResult The result.
   */
  public function __invoke(IntrospectTokenQuery $query): IntrospectTokenResult
  {
    try {
      if ($query->tokenTypeHint === 'refresh_token') {
        return $this->introspectRefreshToken($query->token);
      }

      return $this->introspectAccessToken($query->token);
    } catch (Throwable) {
      return IntrospectTokenResult::inactive();
    }
  }

  /**
   * Method introspectAccessToken
   *
   * Introspects an access token.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $token The token.
   *
   * @return IntrospectTokenResult The result.
   */
  private function introspectAccessToken(string $token): IntrospectTokenResult
  {
    if ($token === '') {
      return IntrospectTokenResult::inactive();
    }

    $tokenData = $this->jwtParser->parse($token);
    if ($tokenData === null) {
      return IntrospectTokenResult::inactive();
    }

    $tokenId = $tokenData['jti'] ?? null;
    if ($tokenId === null || !is_string($tokenId)) {
      return IntrospectTokenResult::inactive();
    }

    // Check cache first
    $cached = $this->tokenCache->get($tokenId);
    if ($cached !== null) {
      return $this->buildResultFromCache($cached);
    }

    $accessToken = $this->accessTokenRepository->find($tokenId);
    if ($accessToken === null || $accessToken->isRevoked() || $accessToken->isExpired()) {
      return IntrospectTokenResult::inactive();
    }

    $result = new IntrospectTokenResult(
      active: true,
      scope: implode(' ', $accessToken->scopes()->toArray()),
      clientId: (string) $accessToken->clientIdentifier(),
      tokenType: 'Bearer',
      exp: $accessToken->expiry()->getTimestamp(),
      iat: $tokenData['iat'] ?? null,
      nbf: $tokenData['nbf'] ?? null,
      sub: $accessToken->userIdentifier(),
      iss: $this->issuer,
      jti: $tokenId,
    );

    // Cache the result (TTL = time until expiry, max 5 minutes)
    $ttl = min(300, $accessToken->expiry()->getTimestamp() - time());
    if ($ttl > 0) {
      $this->tokenCache->set($tokenId, $this->resultToArray($result), $ttl);
    }

    return $result;
  }

  /**
   * Method buildResultFromCache
   *
   * Builds result from cached data.
   *
   * @access private
   * @since 2.0.0
   *
   * @param array<string, mixed> $cached The cached data.
   *
   * @return IntrospectTokenResult The result.
   */
  private function buildResultFromCache(array $cached): IntrospectTokenResult
  {
    // Check if still active (expiry might have passed)
    $exp = $cached['exp'] ?? 0;
    if ($exp < time()) {
      return IntrospectTokenResult::inactive();
    }

    return new IntrospectTokenResult(
      active: (bool) ($cached['active'] ?? false),
      scope: $cached['scope'] ?? null,
      clientId: $cached['client_id'] ?? null,
      tokenType: $cached['token_type'] ?? null,
      exp: $cached['exp'] ?? null,
      iat: $cached['iat'] ?? null,
      nbf: $cached['nbf'] ?? null,
      sub: $cached['sub'] ?? null,
      iss: $cached['iss'] ?? null,
      jti: $cached['jti'] ?? null,
    );
  }

  /**
   * Method resultToArray
   *
   * Converts result to cacheable array.
   *
   * @access private
   * @since 2.0.0
   *
   * @param IntrospectTokenResult $result The result.
   *
   * @return array<string, mixed> The array.
   */
  private function resultToArray(IntrospectTokenResult $result): array
  {
    return [
      'active' => $result->active,
      'scope' => $result->scope,
      'client_id' => $result->clientId,
      'token_type' => $result->tokenType,
      'exp' => $result->exp,
      'iat' => $result->iat,
      'nbf' => $result->nbf,
      'sub' => $result->sub,
      'iss' => $result->iss,
      'jti' => $result->jti,
    ];
  }

  /**
   * Method introspectRefreshToken
   *
   * Introspects a refresh token.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $token The token.
   *
   * @return IntrospectTokenResult The result.
   */
  private function introspectRefreshToken(string $token): IntrospectTokenResult
  {
    $refreshToken = $this->refreshTokenRepository->find($token);

    if ($refreshToken === null || $refreshToken->isRevoked()) {
      return IntrospectTokenResult::inactive();
    }

    if ($refreshToken->expiryDateTime() < new DateTimeImmutable()) {
      return IntrospectTokenResult::inactive();
    }

    return new IntrospectTokenResult(
      active: true,
      tokenType: 'refresh_token',
      exp: $refreshToken->expiryDateTime()->getTimestamp(),
      iss: $this->issuer,
      jti: $refreshToken->identifier(),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function execute(IntrospectTokenQuery $query): IntrospectTokenResult
  {
    return $this->__invoke($query);
  }
  //#endregion
}
