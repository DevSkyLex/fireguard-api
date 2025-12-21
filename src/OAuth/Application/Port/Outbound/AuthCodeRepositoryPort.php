<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

use OAuth\Domain\Model\AuthCode;

/**
 * Interface AuthCodeRepositoryPort.
 *
 * Port for Auth Code repository.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AuthCodeRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Saves an auth code.
   *
   * @since 1.0.0
   *
   * @param AuthCode $authCode the auth code to save
   */
  public function save(AuthCode $authCode): void;

  /**
   * Method find.
   *
   * Finds an auth code by identifier.
   *
   * @since 1.0.0
   *
   * @param string $identifier the auth code identifier
   *
   * @return AuthCode|null the auth code or null if not found
   */
  public function find(string $identifier): ?AuthCode;
  // #endregion
}
