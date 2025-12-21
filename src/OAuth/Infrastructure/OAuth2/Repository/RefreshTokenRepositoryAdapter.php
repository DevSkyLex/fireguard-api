<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\Repository;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use OAuth\Application\Port\Outbound\RefreshTokenRepositoryPort;
use OAuth\Domain\Model\RefreshToken;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;
use OAuth\Infrastructure\OAuth2\Entity\RefreshToken as LeagueRefreshToken;

/**
 * Repository RefreshTokenRepositoryAdapter.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RefreshTokenRepositoryAdapter implements RefreshTokenRepositoryInterface
{
  // #region Constructor
  public function __construct(
    private RefreshTokenRepositoryPort $refreshTokenRepository,
  ) {
  }
  // #endregion

  // #region Methods
  public function getNewRefreshToken(): RefreshTokenEntityInterface
  {
    return new LeagueRefreshToken();
  }

  public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
  {
    $token = new RefreshToken(
      identifier: $refreshTokenEntity->getIdentifier(),
      expiryDateTime: DateTimeImmutable::createFromInterface($refreshTokenEntity->getExpiryDateTime()),
      accessTokenIdentifier: $refreshTokenEntity->getAccessToken()->getIdentifier(),
      clientIdentifier: new OAuthClientIdentifier((string) $refreshTokenEntity->getAccessToken()->getClient()->getIdentifier()),
    );

    $this->refreshTokenRepository->save($token);
  }

  public function revokeRefreshToken(string $tokenId): void
  {
    $token = $this->refreshTokenRepository->find($tokenId);

    if ($token) {
      $token->revoke();
      $this->refreshTokenRepository->save($token);
    }
  }

  public function isRefreshTokenRevoked(string $tokenId): bool
  {
    $token = $this->refreshTokenRepository->find($tokenId);

    if (!$token) {
      return true;
    }

    return $token->isRevoked();
  }
  // #endregion
}
