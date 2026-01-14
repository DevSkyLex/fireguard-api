<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Auth;

use Auth\Application\Contract\Token\AccessTokenStatus;
use Auth\Application\Port\Outbound\AccessTokenLookupPort;
use OAuth\Application\Port\Outbound\Token\AccessTokenRepositoryPort;

/**
 * Adapter AccessTokenLookupAdapter.
 *
 * Bridges the Auth module access token lookup
 * with OAuth token storage.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AccessTokenLookupAdapter implements AccessTokenLookupPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AccessTokenRepositoryPort $accessTokenRepository the access token repository
   */
  public function __construct(
    private AccessTokenRepositoryPort $accessTokenRepository,
  ) {
  }
  // #endregion

  // #region Methods
  public function find(string $identifier): ?AccessTokenStatus
  {
    $token = $this->accessTokenRepository->find($identifier);

    if (null === $token) {
      return null;
    }

    return new AccessTokenStatus(
      scopes: $token->scopes()->toArray(),
      revoked: $token->isRevoked(),
      expired: $token->isExpired(),
    );
  }
  // #endregion
}
