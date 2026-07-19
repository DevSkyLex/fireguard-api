<?php

declare(strict_types=1);

namespace Webhook\Application\Port\Outbound;

/**
 * Port WebhookSecretCipherPort.
 *
 * Reversible encryption for the webhook signing secret — deliberately NOT
 * a one-way hash (unlike {@see \Shared\Domain\ValueObject\HashedSecret}
 * used for OAuth client secrets): HMAC signing must reproduce the
 * plaintext secret on every delivery attempt, so it is encrypted at rest
 * rather than hashed. See `Webhook\Infrastructure\Adapter\Crypto\OpensslWebhookSecretCipherAdapter`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface WebhookSecretCipherPort
{
  // #region Methods
  /**
   * Method encrypt.
   *
   * @since 1.0.0
   *
   * @param string $plaintext the plaintext signing secret
   *
   * @return string the ciphertext to persist
   */
  public function encrypt(string $plaintext): string;

  /**
   * Method decrypt.
   *
   * @since 1.0.0
   *
   * @param string $ciphertext the persisted ciphertext
   *
   * @return string the decrypted plaintext signing secret
   */
  public function decrypt(string $ciphertext): string;
  // #endregion
}
