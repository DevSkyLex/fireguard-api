<?php

declare(strict_types=1);

namespace Auth\Infrastructure\League\Adapter\Outbound;

use Auth\Application\Port\Outbound\AccessTokenRepositoryPort;
use Auth\Domain\Model\AccessToken;
use Auth\Infrastructure\League\Model\AccessToken as LeagueAccessToken;
use DateTimeImmutable;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Shared\Domain\ValueObject\OAuthClientIdentifier;
use Shared\Domain\ValueObject\Scopes;

/**
 * Adapter AccessTokenRepositoryAdapter
 * @final
 *
 * Adapter for League AccessTokenRepositoryInterface.
 *
 * @category Adapter
 * @package Auth\Infrastructure\League\Adapter\Outbound
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
   * Initialize the adapter with the 
   * domain access token repository.
   * 
   * @access private
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
   * Get a new access token.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param ClientEntityInterface $clientEntity The client entity.
   * @param array<mixed> $scopes The scopes.
   * @param string|null $userIdentifier The user identifier.
   * 
   * @return AccessTokenEntityInterface The access token entity.
   */
  public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null): AccessTokenEntityInterface
  {
    $token = new LeagueAccessToken();
    $token->setClient(client: $clientEntity);

    foreach ($scopes as $scope) {
      $token->addScope($scope);
    }

    if (!empty($userIdentifier)) {
      $token->setUserIdentifier(identifier: $userIdentifier);
    }

    return $token;
  }

  /**
   * Method persistNewAccessToken
   * {@inheritDoc}
   * 
   * Persist a new access token.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param AccessTokenEntityInterface $accessTokenEntity The access token entity to persist.
   * 
   * @return void No return value.
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
   * Revoke an access token.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param string $tokenId The access token identifier.
   * 
   * @return void No return value.
   */
  public function revokeAccessToken($tokenId): void
  {
    $token = $this->accessTokenRepository->find(identifier: $tokenId);

    if ($token) {
      $token->revoke();
      $this->accessTokenRepository->save(accessToken: $token);
    }
  }

  /**
   * Method isAccessTokenRevoked
   * {@inheritDoc}
   * 
   * Check if an access token is revoked.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param string $tokenId The access token identifier.
   * 
   * @return bool True if the access token is revoked, false otherwise.
   */
  public function isAccessTokenRevoked($tokenId): bool
  {
    $token = $this->accessTokenRepository->find(identifier: $tokenId);

    if (!$token) return true;
    
    return $token->isRevoked();
  }
  //#endregion
}
