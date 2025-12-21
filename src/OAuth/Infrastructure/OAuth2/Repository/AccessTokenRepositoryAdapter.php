<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\Repository;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\{
  AccessTokenEntityInterface,
  ClientEntityInterface,
  ScopeEntityInterface
};
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use OAuth\Application\Port\Outbound\AccessTokenRepositoryPort;
use OAuth\Domain\Model\AccessToken;
use OAuth\Domain\ValueObject\{OAuthClientIdentifier, Scopes};
use OAuth\Infrastructure\OAuth2\Entity\AccessToken as LeagueAccessToken;

/**
 * Repository AccessTokenRepositoryAdapter.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AccessTokenRepositoryAdapter implements AccessTokenRepositoryInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the AccessTokenRepositoryAdapter.
   *
   * @since 1.0.0
   *
   * @param AccessTokenRepositoryPort $accessTokenRepository the domain access token repository
   */
  public function __construct(
    private readonly AccessTokenRepositoryPort $accessTokenRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method getNewToken
   * {@inheritDoc}
   *
   * Get new access token.
   *
   * @since 1.0.0
   *
   * @param ClientEntityInterface $clientEntity the client entity
   * @param array<ScopeEntityInterface> $scopes the scopes
   * @param string|int|null $userIdentifier the user identifier
   *
   * @return AccessTokenEntityInterface the access token entity
   */
  public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null): AccessTokenEntityInterface
  {
    $token = new LeagueAccessToken();
    $token->setClient(client: $clientEntity);

    foreach ($scopes as $scope) {
      $token->addScope(scope: $scope);
    }

    if (!empty($userIdentifier)) {
      $token->setUserIdentifier(identifier: (string) $userIdentifier);
    }

    return $token;
  }

  /**
   * Method persistNewAccessToken
   * {@inheritDoc}
   *
   * Persist new access token.
   *
   * @since 1.0.0
   *
   * @param AccessTokenEntityInterface $accessTokenEntity the access token entity
   */
  public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
  {
    $scopes = [];

    foreach ($accessTokenEntity->getScopes() as $scope) {
      $scopes[] = $scope->getIdentifier();
    }

    $token = new AccessToken(
      identifier: $accessTokenEntity->getIdentifier(),
      clientIdentifier: new OAuthClientIdentifier($accessTokenEntity->getClient()->getIdentifier()),
      expiry: DateTimeImmutable::createFromInterface($accessTokenEntity->getExpiryDateTime()),
      scopes: Scopes::fromArray($scopes),
      userIdentifier: $accessTokenEntity->getUserIdentifier(),
      isRevoked: false,
    );

    $this->accessTokenRepository->save(accessToken: $token);
  }

  /**
   * Method revokeAccessToken
   * {@inheritDoc}
   *
   * Revoke an access token by its ID.
   *
   * @since 1.0.0
   *
   * @param string $tokenId the ID of the token to revoke
   *
   * @return void No return value
   */
  public function revokeAccessToken(string $tokenId): void
  {
    $token = $this->accessTokenRepository->find(identifier: $tokenId);

    if (!$token) {
      return;
    }

    $token->revoke();
    $this->accessTokenRepository->save(accessToken: $token);
  }

  /**
   * Method isAccessTokenRevoked
   * {@inheritdoc}
   *
   * Check if the access token is revoked.
   *
   * @since 1.0.0
   *
   * @param string $tokenId The access token
   *
   * @return bool True if revoked
   */
  public function isAccessTokenRevoked(string $tokenId): bool
  {
    $token = $this->accessTokenRepository->find(identifier: $tokenId);

    if (!$token) {
      return true;
    }

    return $token->isRevoked();
  }
  // #endregion
}
