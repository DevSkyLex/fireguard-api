<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Adapter\Crypto;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webhook\Application\Port\Outbound\WebhookSecretCipherPort;

use function base64_decode;
use function base64_encode;
use function mb_strlen;
use function openssl_decrypt;
use function openssl_encrypt;
use function random_bytes;
use function substr;

use const OPENSSL_RAW_DATA;

/**
 * Adapter OpensslWebhookSecretCipherAdapter.
 *
 * AES-256-GCM reversible encryption for the webhook signing secret. This
 * is DELIBERATELY reversible — unlike `Shared\Domain\ValueObject\HashedSecret`
 * used for OAuth client secrets (one-way, verify-only) — because HMAC
 * signing must reproduce the plaintext secret on every delivery attempt.
 * A reviewer must NOT "harden" this into a one-way hash.
 *
 * Ciphertext layout (base64 of the concatenation): 12-byte nonce + 16-byte
 * GCM authentication tag + ciphertext. `WEBHOOK_ENCRYPTION_KEY` (raw bytes,
 * exactly 32 for AES-256) must never be logged and, if rotated, invalidates
 * every previously stored secret (see `MODULE.md`'s key-rotation runbook).
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OpensslWebhookSecretCipherAdapter implements WebhookSecretCipherPort
{
  // #region Constants
  private const string CIPHER_METHOD = 'aes-256-gcm';

  private const int NONCE_LENGTH = 12;

  private const int TAG_LENGTH = 16;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $encryptionKey the raw 32-byte AES-256 key
   */
  public function __construct(
    #[Autowire('%env(base64:WEBHOOK_ENCRYPTION_KEY)%')]
    private string $encryptionKey,
  ) {
  }
  // #endregion

  // #region Methods
  public function encrypt(string $plaintext): string
  {
    $nonce = random_bytes(self::NONCE_LENGTH);
    $tag = '';

    $ciphertext = openssl_encrypt(
      data: $plaintext,
      cipher_algo: self::CIPHER_METHOD,
      passphrase: $this->encryptionKey,
      options: OPENSSL_RAW_DATA,
      iv: $nonce,
      tag: $tag,
      tag_length: self::TAG_LENGTH,
    );

    if (false === $ciphertext) {
      throw new RuntimeException('Failed to encrypt the webhook signing secret.');
    }

    return base64_encode($nonce . $tag . $ciphertext);
  }

  public function decrypt(string $ciphertext): string
  {
    $raw = base64_decode($ciphertext, true);

    if (false === $raw || mb_strlen($raw, '8bit') <= self::NONCE_LENGTH + self::TAG_LENGTH) {
      throw new RuntimeException('Malformed webhook secret ciphertext.');
    }

    $nonce = substr($raw, 0, self::NONCE_LENGTH);
    $tag = substr($raw, self::NONCE_LENGTH, self::TAG_LENGTH);
    $encrypted = substr($raw, self::NONCE_LENGTH + self::TAG_LENGTH);

    $plaintext = openssl_decrypt(
      data: $encrypted,
      cipher_algo: self::CIPHER_METHOD,
      passphrase: $this->encryptionKey,
      options: OPENSSL_RAW_DATA,
      iv: $nonce,
      tag: $tag,
    );

    if (false === $plaintext) {
      throw new RuntimeException('Failed to decrypt the webhook signing secret — the ciphertext may have been tampered with, or WEBHOOK_ENCRYPTION_KEY has changed.');
    }

    return $plaintext;
  }
  // #endregion
}
