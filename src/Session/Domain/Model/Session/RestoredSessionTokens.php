<?php

declare(strict_types=1);

namespace Session\Domain\Model\Session;

/**
 * Current token identifiers restored from a persisted session.
 *
 * @category Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoredSessionTokens
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries token record identifiers associated with a restored session.
   *
   * @access public
   *
   * @param ?string $accessTokenId optional access-token record identifier
   * @param ?string $refreshTokenId optional refresh-token record identifier
   *
   * @return void
   */
  public function __construct(
    public ?string $accessTokenId,
    public ?string $refreshTokenId,
  ) {
  }
  // #endregion
}
