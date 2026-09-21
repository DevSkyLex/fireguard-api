<?php

declare(strict_types=1);

namespace Otp\Application\Port\Outbound\Totp;

/**
 * Port TotpSecretCipherPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TotpSecretCipherPort
{
  /**
   * Checks whether a dedicated write key has been provisioned.
   */
  public function canEncrypt(): bool;

  /**
   * Encrypts a secret, binding it to the enrollment and active/pending slot.
   */
  public function encrypt(string $plaintext, string $context): string;

  /**
   * Authenticates and decrypts a versioned envelope, including retained old keys.
   */
  public function decrypt(string $ciphertext, string $context): string;

  /**
   * Reports whether an envelope must be rewritten with the active key.
   */
  public function needsRotation(string $ciphertext): bool;
}
