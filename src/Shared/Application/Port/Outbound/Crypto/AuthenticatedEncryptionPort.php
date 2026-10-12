<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Crypto;

/**
 * Interface AuthenticatedEncryptionPort
 *
 * Encrypts with a dedicated versioned keyring and caller-bound authenticated data.
 *
 * @category Outbound Port
 */
interface AuthenticatedEncryptionPort
{
  // #region Methods
  /**
   * Method encrypt
   *
   * @access public
   *
   * @param string $plaintext transient secret bytes, at most 8 MiB
   * @param string $authenticatedData nonempty identity and purpose binding
   *
   * @return string versioned authenticated envelope
   */
  public function encrypt(string $plaintext, string $authenticatedData): string;

  /**
   * Method decrypt
   *
   * @access public
   *
   * @param string $ciphertext versioned authenticated envelope
   * @param string $authenticatedData original identity and purpose binding
   *
   * @return string authenticated plaintext
   */
  public function decrypt(string $ciphertext, string $authenticatedData): string;
  // #endregion
}
