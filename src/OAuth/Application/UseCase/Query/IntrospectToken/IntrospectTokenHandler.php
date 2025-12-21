<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\IntrospectToken;

use DateTimeImmutable;
use OAuth\Application\Port\Outbound\AccessTokenRepositoryPort;
use OAuth\Application\Port\Outbound\JwtParserPort;
use OAuth\Application\Port\Outbound\RefreshTokenRepositoryPort;
use OAuth\Application\Port\Outbound\TokenCachePort;
use Shared\Application\Message\QueryHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function implode;
use function is_int;
use function is_string;
use function min;
use function time;

/**
 * Handler IntrospectTokenHandler.
 *
 * @category Handler
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IntrospectTokenHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * IntrospectTokenHandler class.
   *
   * @since 1.0.0
   *
   * @param JwtParserPort              $jwtParser              the JWT parser
   * @param AccessTokenRepositoryPort  $accessTokenRepository  the access token repository
   * @param RefreshTokenRepositoryPort $refreshTokenRepository the refresh token repository
   * @param TokenCachePort             $tokenCache             the token cache
   * @param string                     $issuer                 the token issuer
   */
  public function __construct(
    private readonly JwtParserPort $jwtParser,
    private readonly AccessTokenRepositoryPort $accessTokenRepository,
    private readonly RefreshTokenRepositoryPort $refreshTokenRepository,
    private readonly TokenCachePort $tokenCache,
    #[Autowire('%env(OAUTH_ISSUER)%')]
    private readonly string $issuer = '',
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the IntrospectTokenQuery.
   *
   * @since 1.0.0
   *
   * @param IntrospectTokenQuery $query the query
   *
   * @return IntrospectTokenResult the result
   */
  public function __invoke(IntrospectTokenQuery $query): IntrospectTokenResult
  {
    try {
      if ('refresh_token' === $query->tokenTypeHint) {
        return $this->introspectRefreshToken($query->token);
      }

      return $this->introspectAccessToken($query->token);
    } catch (Throwable) {
      return IntrospectTokenResult::inactive();
    }
  }

  /**
   * Method introspectAccessToken.
   *
   * Introspects an access token.
   *
   * @since 1.0.0
   *
   * @param string $token the token
   *
   * @return IntrospectTokenResult the result
   */
  private function introspectAccessToken(string $token): IntrospectTokenResult
  {
    if ('' === $token) {
      return IntrospectTokenResult::inactive();
    }

    $tokenData = $this->jwtParser->parse($token);
    if (null === $tokenData) {
      return IntrospectTokenResult::inactive();
    }

    $tokenId = $tokenData['jti'] ?? null;
    if (null === $tokenId || !is_string($tokenId)) {
      return IntrospectTokenResult::inactive();
    }

    // Check cache first
    $cached = $this->tokenCache->get($tokenId);
    if (null !== $cached) {
      return $this->buildResultFromCache($cached);
    }

    $accessToken = $this->accessTokenRepository->find($tokenId);
    if (null === $accessToken || $accessToken->isRevoked() || $accessToken->isExpired()) {
      return IntrospectTokenResult::inactive();
    }

    $iat = $tokenData['iat'] ?? null;
    $nbf = $tokenData['nbf'] ?? null;

    $result = new IntrospectTokenResult(
      active: true,
      scope: implode(' ', $accessToken->scopes()->toArray()),
      clientId: (string) $accessToken->clientIdentifier(),
      tokenType: 'Bearer',
      exp: $accessToken->expiry()->getTimestamp(),
      iat: is_int($iat) ? $iat : null,
      nbf: is_int($nbf) ? $nbf : null,
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
   * Method buildResultFromCache.
   *
   * Builds result from cached data.
   *
   * @since 2.0.0
   *
   * @param array<string, mixed> $cached the cached data
   *
   * @return IntrospectTokenResult the result
   */
  private function buildResultFromCache(array $cached): IntrospectTokenResult
  {
    // Check if still active (expiry might have passed)
    $exp = $cached['exp'] ?? 0;
    $expInt = is_int($exp) ? $exp : 0;
    if ($expInt < time()) {
      return IntrospectTokenResult::inactive();
    }

    $scope = $cached['scope'] ?? null;
    $clientId = $cached['client_id'] ?? null;
    $tokenType = $cached['token_type'] ?? null;
    $iat = $cached['iat'] ?? null;
    $nbf = $cached['nbf'] ?? null;
    $sub = $cached['sub'] ?? null;
    $iss = $cached['iss'] ?? null;
    $jti = $cached['jti'] ?? null;

    return new IntrospectTokenResult(
      active: (bool) ($cached['active'] ?? false),
      scope: is_string($scope) ? $scope : null,
      clientId: is_string($clientId) ? $clientId : null,
      tokenType: is_string($tokenType) ? $tokenType : null,
      exp: is_int($exp) ? $exp : null,
      iat: is_int($iat) ? $iat : null,
      nbf: is_int($nbf) ? $nbf : null,
      sub: is_string($sub) ? $sub : null,
      iss: is_string($iss) ? $iss : null,
      jti: is_string($jti) ? $jti : null,
    );
  }

  /**
   * Method resultToArray.
   *
   * Converts result to cacheable array.
   *
   * @since 2.0.0
   *
   * @param IntrospectTokenResult $result the result
   *
   * @return array<string, mixed> the array
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
   * Method introspectRefreshToken.
   *
   * Introspects a refresh token.
   *
   * @since 1.0.0
   *
   * @param string $token the token
   *
   * @return IntrospectTokenResult the result
   */
  private function introspectRefreshToken(string $token): IntrospectTokenResult
  {
    $refreshToken = $this->refreshTokenRepository->find($token);

    if (null === $refreshToken || $refreshToken->isRevoked()) {
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

  // #endregion
}
