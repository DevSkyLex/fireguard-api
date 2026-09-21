<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Crypto;

use Otp\Application\Port\Outbound\Totp\TotpSecretCipherPort;
use RuntimeException;
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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OpensslTotpSecretCipherAdapter implements TotpSecretCipherPort
{
  /**
   * @var array<string, string> raw keys, never logged
   */
  private array $keys;

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
        throw new RuntimeException('Invalid TOTP encryption key configuration.');
      }
      $key = base64_decode($encoded, true);
      if (false === $key || 32 !== strlen($key)) {
        throw new RuntimeException('TOTP encryption keys must contain exactly 32 bytes.');
      }
      $keys[$id] = $key;
    }
    if ('' !== $writeKeyId && !isset($keys[$writeKeyId])) {
      throw new RuntimeException('The TOTP write key is not available.');
    }
    $this->keys = $keys;
  }

  public function canEncrypt(): bool
  {
    return '' !== $this->writeKeyId;
  }

  public function encrypt(#[SensitiveParameter] string $plaintext, string $context): string
  {
    if (!$this->canEncrypt()) {
      throw new RuntimeException('A dedicated TOTP write key must be provisioned.');
    }
    $prefix = 'totp:v1:' . $this->writeKeyId . ':';
    $nonce = random_bytes(12);
    $tag = '';
    $encrypted = openssl_encrypt($plaintext, 'aes-256-gcm', $this->keys[$this->writeKeyId], OPENSSL_RAW_DATA, $nonce, $tag, $prefix . $context, 16);
    if (false === $encrypted) {
      throw new RuntimeException('TOTP secret encryption failed.');
    }

    return $prefix . base64_encode($nonce . $tag . $encrypted);
  }

  public function decrypt(#[SensitiveParameter] string $ciphertext, string $context): string
  {
    $parts = explode(':', $ciphertext, 4);
    if (4 !== count($parts) || 'totp' !== $parts[0] || 'v1' !== $parts[1] || !isset($this->keys[$parts[2]])) {
      throw new RuntimeException('Unsupported TOTP secret envelope or unavailable key.');
    }
    $raw = base64_decode($parts[3], true);
    if (false === $raw || strlen($raw) <= 28) {
      throw new RuntimeException('Invalid TOTP secret envelope.');
    }
    $plaintext = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->keys[$parts[2]], OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'totp:v1:' . $parts[2] . ':' . $context);
    if (false === $plaintext) {
      throw new RuntimeException('TOTP secret authentication failed.');
    }

    return $plaintext;
  }

  public function needsRotation(string $ciphertext): bool
  {
    return !$this->canEncrypt() || !str_starts_with($ciphertext, 'totp:v1:' . $this->writeKeyId . ':');
  }
}
