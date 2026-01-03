<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound\Token;

use OAuth\Domain\Model\Token\AuthCode;

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
   *
   * @return void no return value
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

  /**
   * Method findByEncryptedCode.
   *
   * Finds an auth code by its encrypted authorization code value.
   *
   * @since 1.0.0
   *
   * @param string $encryptedCode the encrypted authorization code
   *
   * @return AuthCode|null the auth code or null if not found
   */
  public function findByEncryptedCode(string $encryptedCode): ?AuthCode;

  /**
   * Method updateNonce.
   *
   * Updates the nonce associated with an auth code.
   *
   * @since 1.0.0
   *
   * @param string $identifier the auth code identifier
   * @param string|null $nonce the nonce value
   *
   * @return void no return value
   */
  public function updateNonce(string $identifier, ?string $nonce): void;
  // #endregion
}
