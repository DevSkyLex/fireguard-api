<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\Repository;

use OAuth\Application\Port\Outbound\RefreshTokenRepositoryPort;
use OAuth\Domain\Model\RefreshToken;
use OAuth\Infrastructure\OAuth2\Entity\RefreshToken as LeagueRefreshToken;
use DateTimeImmutable;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;

/**
 * Repository RefreshTokenRepositoryAdapter
 * @final
 *
 * Adapter implementing League's RefreshTokenRepositoryInterface.
 *
 * @category Repository
 * @package OAuth\Infrastructure\OAuth2\Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RefreshTokenRepositoryAdapter implements RefreshTokenRepositoryInterface
{
  //#region Constructor
  public function __construct(
    private RefreshTokenRepositoryPort $refreshTokenRepository
  ) {}
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
   */
  public function getNewRefreshToken(): RefreshTokenEntityInterface
  {
    return new LeagueRefreshToken();
  }

  /**
   * {@inheritDoc}
   */
  public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
  {
    $token = new RefreshToken(
      identifier: $refreshTokenEntity->getIdentifier(),
      expiryDateTime: DateTimeImmutable::createFromInterface($refreshTokenEntity->getExpiryDateTime()),
      accessTokenIdentifier: $refreshTokenEntity->getAccessToken()->getIdentifier(),
      clientIdentifier: new OAuthClientIdentifier((string) $refreshTokenEntity->getAccessToken()->getClient()->getIdentifier())
    );

    $this->refreshTokenRepository->save($token);
  }

  /**
   * {@inheritDoc}
   */
  /**
   * @param string $tokenId
   */
  public function revokeRefreshToken($tokenId): void
  {
    $token = $this->refreshTokenRepository->find($tokenId);

    if ($token) {
      $token->revoke();
      $this->refreshTokenRepository->save($token);
    }
  }

  /**
   * {@inheritDoc}
   */
  /**
   * @param string $tokenId
   */
  public function isRefreshTokenRevoked($tokenId): bool
  {
    $token = $this->refreshTokenRepository->find($tokenId);

    if (!$token) {
      return true;
    }

    return $token->isRevoked();
  }
  //#endregion
}
