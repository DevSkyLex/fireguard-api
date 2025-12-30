<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\League\Repository;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use OAuth\Application\Port\Outbound\Token\RefreshTokenRepositoryPort;
use OAuth\Domain\Model\Token\RefreshToken;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Infrastructure\OAuth2\League\Entity\RefreshToken as LeagueRefreshToken;

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
  /**
   * Constructor.
   *
   * Initialize the RefreshTokenRepositoryAdapter.
   *
   * @since 1.0.0
   *
   * @param RefreshTokenRepositoryPort $refreshTokenRepository the domain refresh token repository
   */
  public function __construct(
    private readonly RefreshTokenRepositoryPort $refreshTokenRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method getNewRefreshToken
   * {@inheritDoc}
   *
   * Get new refresh token entity.
   *
   * @since 1.0.0
   *
   * @return RefreshTokenEntityInterface the refresh token entity
   */
  public function getNewRefreshToken(): RefreshTokenEntityInterface
  {
    return new LeagueRefreshToken();
  }

  /**
   * Method persistNewRefreshToken
   * {@inheritDoc}
   *
   * Persist new refresh token.
   *
   * @since 1.0.0
   *
   * @param RefreshTokenEntityInterface $refreshTokenEntity the refresh token entity
   *
   * @return void No return value
   */
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

  /**
   * Method revokeRefreshToken
   * {@inheritDoc}
   *
   * Revoke a refresh token by its ID.
   *
   * @since 1.0.0
   *
   * @param string $tokenId the ID of the token to revoke
   *
   * @return void No return value
   */
  public function revokeRefreshToken(string $tokenId): void
  {
    $token = $this->refreshTokenRepository->find($tokenId);

    if ($token) {
      $token->revoke();
      $this->refreshTokenRepository->save($token);
    }
  }

  /**
   * Method isRefreshTokenRevoked
   * {@inheritdoc}
   *
   * Check if the refresh token is revoked.
   *
   * @since 1.0.0
   *
   * @param string $tokenId the refresh token identifier
   *
   * @return bool True if revoked
   */
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
