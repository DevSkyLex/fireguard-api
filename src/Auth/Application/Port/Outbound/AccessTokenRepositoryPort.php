<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

use Auth\Domain\Model\AccessToken;

/**
 * Interface AccessTokenRepositoryPort
 *
 * Port for Access Token persistence.
 *
 * @category Port
 * @package Auth\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AccessTokenRepositoryPort
{
  /**
   * Method save
   *
   * Saves an access token.
   *
   * @param AccessToken $accessToken The access token to save.
   * @return void
   */
  public function save(AccessToken $accessToken): void;

  /**
   * Method find
   *
   * Finds an access token by its identifier.
   *
   * @param string $identifier The token identifier.
   * @return AccessToken|null The access token or null if not found.
   */
  public function find(string $identifier): ?AccessToken;
}
