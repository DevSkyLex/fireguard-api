<?php

declare(strict_types=1);

namespace Auth\Infrastructure\League\Adapter\Outbound;

use Auth\Application\Port\Outbound\RefreshTokenRepositoryPort;
use Auth\Domain\Model\RefreshToken;
use Auth\Infrastructure\League\Model\RefreshToken as LeagueRefreshToken;
use DateTimeImmutable;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Shared\Domain\ValueObject\OAuthClientIdentifier;

/**
 * Adapter RefreshTokenRepositoryAdapter
 * @final
 *
 * Adapter for League RefreshTokenRepositoryInterface.
 *
 * @category Adapter
 * @package Auth\Infrastructure\League\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RefreshTokenRepositoryAdapter implements RefreshTokenRepositoryInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * RefreshTokenRepositoryAdapter class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RefreshTokenRepositoryPort $refreshTokenRepository The domain refresh token repository.
   */
  public function __construct(
    private RefreshTokenRepositoryPort $refreshTokenRepository
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method getNewRefreshToken
   * {@inheritDoc}
   *
   * Get a new refresh token.
   *
   * @access public
   * @since 1.0.0
   *
   * @return RefreshTokenEntityInterface The refresh token entity.
   */
  public function getNewRefreshToken(): RefreshTokenEntityInterface
  {
    return new LeagueRefreshToken();
  }

  /**
   * Method persistNewRefreshToken
   * {@inheritDoc}
   *
   * Persist a new refresh token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RefreshTokenEntityInterface $refreshTokenEntity The refresh token entity.
   *
   * @return void
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
   * Method revokeRefreshToken
   * {@inheritDoc}
   *
   * Revoke a refresh token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $tokenId The refresh token identifier.
   *
   * @return void
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
   * Method isRefreshTokenRevoked
   * {@inheritDoc}
   *
   * Check if a refresh token is revoked.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $tokenId The refresh token identifier.
   *
   * @return bool True if revoked, false otherwise.
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
