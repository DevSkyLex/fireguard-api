<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound\Token;

use OAuth\Domain\Model\Token\AccessToken;

/**
 * Interface AccessTokenRepositoryPort.
 *
 * Port for Access Token persistence.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AccessTokenRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Saves an access token.
   *
   * @since 1.0.0
   *
   * @param AccessToken $accessToken the access token to save
   *
   * @return void No return value
   */
  public function save(AccessToken $accessToken): void;

  /**
   * Method find.
   *
   * Finds an access token by its identifier.
   *
   * @since 1.0.0
   *
   * @param string $identifier the token identifier
   *
   * @return AccessToken|null the access token or null if not found
   */
  public function find(string $identifier): ?AccessToken;
  // #endregion
}
