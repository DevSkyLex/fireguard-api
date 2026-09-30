<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Crypto;

use Otp\Application\Port\Outbound\Totp\TotpSecretCipherPort;
use Otp\Infrastructure\Exception\TotpSecretCipherException;
use SensitiveParameter;

use function base64_decode;
use function base64_encode;
use function count;
use function explode;
use function is_string;
use function openssl_decrypt;
use function openssl_encrypt;
use function preg_match;
use function random_bytes;
use function str_starts_with;
use function strlen;
use function substr;

use const OPENSSL_RAW_DATA;

/**
 * Adapter OpensslTotpSecretCipherAdapter.
 *
 * AES-256-GCM with a dedicated key ring, 96-bit random nonce, 128-bit tag and
 * authenticated version/key/enrollment/slot context. No fallback on decryption
 * failure is permitted, even while legacy plaintext rows remain readable.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OpensslTotpSecretCipherAdapter implements TotpSecretCipherPort
{
  // #region Properties
  /**
   * @var array<string, string> raw keys, never logged
   */
  private array $keys;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $encodedKeys dedicated base64-encoded 32-byte keys
   * @param string $writeKeyId active key identifier; empty during read-compatible rollout
   */
  public function __construct(#[SensitiveParameter] array $encodedKeys, private string $writeKeyId)
  {
    $keys = [];
    foreach ($encodedKeys as $id => $encoded) {
      if (!is_string($id) || 1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $id) || !is_string($encoded)) {
        throw new TotpSecretCipherException('Invalid TOTP encryption key configuration.');
      }
      $key = base64_decode($encoded, true);
      if (false === $key || 32 !== strlen($key)) {
        throw new TotpSecretCipherException('TOTP encryption keys must contain exactly 32 bytes.');
      }
      $keys[$id] = $key;
    }
    if ('' !== $writeKeyId && !isset($keys[$writeKeyId])) {
      throw new TotpSecretCipherException('The TOTP write key is not available.');
    }
    $this->keys = $keys;
  }
  // #endregion

  // #region Methods
  /**
   * Method canEncrypt
   *
   * Reports whether a write key is configured for new TOTP secret ciphertext.
   *
   * @access public
   *
   * @return bool true when a write key is configured
   */
  public function canEncrypt(): bool
  {
    return '' !== $this->writeKeyId;
  }

  /**
   * Method encrypt
   *
   * Encrypts a TOTP secret in the versioned AES-256-GCM envelope using the write key and authenticated slot context.
   *
   * @access public
   *
   * @param string $plaintext the TOTP secret to encrypt
   * @param string $context the user-and-slot binding included as authenticated data
   *
   * @return string
   */
  public function encrypt(#[SensitiveParameter] string $plaintext, string $context): string
  {
    if (!$this->canEncrypt()) {
      throw new TotpSecretCipherException('A dedicated TOTP write key must be provisioned.');
    }
    $prefix = 'totp:v1:' . $this->writeKeyId . ':';
    $nonce = random_bytes(12);
    $tag = '';
    $encrypted = openssl_encrypt($plaintext, 'aes-256-gcm', $this->keys[$this->writeKeyId], OPENSSL_RAW_DATA, $nonce, $tag, $prefix . $context, 16);
    if (false === $encrypted) {
      throw new TotpSecretCipherException('TOTP secret encryption failed.');
    }

    return $prefix . base64_encode($nonce . $tag . $encrypted);
  }

  /**
   * Method decrypt
   *
   * Authenticates and decrypts a versioned TOTP secret envelope; malformed data, an unknown key or a context mismatch fails closed.
   *
   * @access public
   *
   * @param string $ciphertext the versioned encrypted secret envelope
   * @param string $context the user-and-slot binding included as authenticated data
   *
   * @return string
   */
  public function decrypt(#[SensitiveParameter] string $ciphertext, string $context): string
  {
    $parts = explode(':', $ciphertext, 4);
    if (4 !== count($parts) || 'totp' !== $parts[0] || 'v1' !== $parts[1] || !isset($this->keys[$parts[2]])) {
      throw new TotpSecretCipherException('Unsupported TOTP secret envelope or unavailable key.');
    }
    $raw = base64_decode($parts[3], true);
    if (false === $raw || strlen($raw) <= 28) {
      throw new TotpSecretCipherException('Invalid TOTP secret envelope.');
    }
    $plaintext = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->keys[$parts[2]], OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'totp:v1:' . $parts[2] . ':' . $context);
    if (false === $plaintext) {
      throw new TotpSecretCipherException('TOTP secret authentication failed.');
    }

    return $plaintext;
  }

  /**
   * Method needsRotation
   *
   * Reports whether ciphertext is not using the configured write key, or no write key is available.
   *
   * @access public
   *
   * @param string $ciphertext the versioned encrypted secret envelope
   *
   * @return bool true when the ciphertext needs re-encryption with the configured write key
   */
  public function needsRotation(string $ciphertext): bool
  {
    return !$this->canEncrypt() || !str_starts_with($ciphertext, 'totp:v1:' . $this->writeKeyId . ':');
  }
  // #endregion
}
