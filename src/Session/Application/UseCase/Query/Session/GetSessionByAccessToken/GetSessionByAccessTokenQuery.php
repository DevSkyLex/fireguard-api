<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\Session\GetSessionByAccessToken;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetSessionByAccessTokenQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetSessionByAccessTokenQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $accessTokenId the access token identifier carried in the token's `jti` claim
   */
  public function __construct(
    public string $accessTokenId,
  ) {
  }
  // #endregion
}
