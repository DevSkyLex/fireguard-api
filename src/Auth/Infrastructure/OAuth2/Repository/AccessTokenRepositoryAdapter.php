<?php

declare(strict_types=1);

namespace Auth\Infrastructure\OAuth2\Repository;

use Auth\Application\Port\Outbound\AccessTokenRepositoryPort;
use Auth\Domain\Model\AccessToken;
use Auth\Infrastructure\OAuth2\Entity\AccessToken as LeagueAccessToken;
use DateTimeImmutable;
use League\OAuth2\Server\Entities\{
  AccessTokenEntityInterface,
  ClientEntityInterface,
  ScopeEntityInterface
};
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Shared\Domain\ValueObject\{OAuthClientIdentifier, Scopes};

/**
 * Repository AccessTokenRepositoryAdapter
 * @final
*
 * Adapter implementing League's AccessTokenRepositoryInterface
 * using the domain AccessTokenRepositoryPort.
 *
 * @category Repository
 * @package Auth\Infrastructure\OAuth2\Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AccessTokenRepositoryAdapter implements AccessTokenRepositoryInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the AccessTokenRepositoryAdapter.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AccessTokenRepositoryPort $accessTokenRepository The domain access token repository.
   */
  public function __construct(
    private readonly AccessTokenRepositoryPort $accessTokenRepository
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method getNewToken
   * {@inheritDoc}
   *
   * Get new access token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientEntityInterface $clientEntity The client entity.
   * @param array<ScopeEntityInterface> $scopes The scopes.
   * @param string|int|null $userIdentifier The user identifier.
   *
   * @return AccessTokenEntityInterface The access token entity.
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
   * @access public
   * @since 1.0.0
   *
   * @param AccessTokenEntityInterface $accessTokenEntity The access token entity.
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
      isRevoked: false
    );

    $this->accessTokenRepository->save(accessToken: $token);
  }

  /**
   * Method revokeAccessToken
   * {@inheritDoc}
   *
   * Revoke an access token by its ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $tokenId The ID of the token to revoke.
   *
   * @return void No return value
   */
  public function revokeAccessToken(string $tokenId): void
  {
    $token = $this->accessTokenRepository->find(identifier: $tokenId);

    if (!$token) return;

    $token->revoke();
    $this->accessTokenRepository->save(accessToken: $token);
  }

  /**
   * Method isAccessTokenRevoked
   * {@inheritdoc}
   *
   * Check if the access token is revoked.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $tokenId The access token
   *
   * @return bool True if revoked
   */
  public function isAccessTokenRevoked(string $tokenId): bool
  {
    $token = $this->accessTokenRepository->find(identifier: $tokenId);

    if (!$token) return true;

    return $token->isRevoked();
  }
  //#endregion
}
