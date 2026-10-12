<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Crypto;

use InvalidArgumentException;
use JsonException;
use Shared\Application\Contract\Crypto\CiphertextRejectedException;
use Shared\Application\Port\Outbound\Crypto\AuthenticatedEncryptionPort;

use function base64_decode;
use function base64_encode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function openssl_decrypt;
use function openssl_encrypt;
use function preg_match;
use function random_bytes;
use function strlen;

use const JSON_THROW_ON_ERROR;
use const OPENSSL_RAW_DATA;

/**
 * Class VersionedAesGcmAdapter
 *
 * Uses a dedicated AES-256-GCM keyring. Old versions decrypt during rotation.
 *
 * @category Adapter
 */
final readonly class VersionedAesGcmAdapter implements AuthenticatedEncryptionPort
{
  // #region Properties
  /**
   * @var array<string, string> validated binary AES keys
   */
  private array $keys;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param array<array-key, mixed> $base64Keys version-to-base64 keyring, never an application/OAuth key
   * @param string $activeVersion encryption key version
   *
   * @return void
   */
  public function __construct(array $base64Keys, private string $activeVersion)
  {
    $keys = [];
    foreach ($base64Keys as $version => $encoded) {
      if (!is_string($version) || 1 !== preg_match('/^[a-zA-Z0-9_-]{1,32}$/D', $version) || !is_string($encoded)) {
        throw new InvalidArgumentException('The encryption keyring is invalid.');
      }
      $key = base64_decode($encoded, true);
      if (false === $key || 32 !== strlen($key)) {
        throw new InvalidArgumentException('The encryption keyring is invalid.');
      }
      $keys[$version] = $key;
    }
    if (!isset($keys[$activeVersion])) {
      throw new InvalidArgumentException('The active encryption version is unavailable.');
    }
    $this->keys = $keys;
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function encrypt(string $plaintext, string $authenticatedData): string
  {
    if ('' === $authenticatedData) {
      throw new InvalidArgumentException('Authenticated data is required.');
    }
    if (strlen($plaintext) > 8388608) {
      throw new InvalidArgumentException('The encrypted result exceeds its byte budget.');
    }
    $nonce = random_bytes(12);
    $tag = '';
    $boundData = 'fireguard:aes-gcm:v1:' . $this->activeVersion . "\0" . $authenticatedData;
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $this->keys[$this->activeVersion], OPENSSL_RAW_DATA, $nonce, $tag, $boundData, 16);
    if (false === $ciphertext) {
      throw new CiphertextRejectedException();
    }

    return json_encode(['v' => 1, 'k' => $this->activeVersion, 'n' => base64_encode($nonce), 't' => base64_encode($tag), 'c' => base64_encode($ciphertext)], JSON_THROW_ON_ERROR);
  }

  /**
   * {@inheritdoc}
   */
  public function decrypt(string $ciphertext, string $authenticatedData): string
  {
    if ('' === $authenticatedData || strlen($ciphertext) > 16777216) {
      throw new CiphertextRejectedException();
    }

    try {
      $envelope = json_decode($ciphertext, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      throw new CiphertextRejectedException();
    }
    if (!is_array($envelope) || 1 !== ($envelope['v'] ?? null) || !is_string($envelope['k'] ?? null) || !isset($this->keys[$envelope['k']])) {
      throw new CiphertextRejectedException();
    }
    if (!is_string($envelope['n'] ?? null) || !is_string($envelope['t'] ?? null) || !is_string($envelope['c'] ?? null)) {
      throw new CiphertextRejectedException();
    }
    $nonce = base64_decode($envelope['n'], true);
    $tag = base64_decode($envelope['t'], true);
    $bytes = base64_decode($envelope['c'], true);
    if (false === $nonce || false === $tag || false === $bytes || 12 !== strlen($nonce) || 16 !== strlen($tag)) {
      throw new CiphertextRejectedException();
    }
    $boundData = 'fireguard:aes-gcm:v1:' . $envelope['k'] . "\0" . $authenticatedData;
    $plaintext = openssl_decrypt($bytes, 'aes-256-gcm', $this->keys[$envelope['k']], OPENSSL_RAW_DATA, $nonce, $tag, $boundData);
    if (false === $plaintext) {
      throw new CiphertextRejectedException();
    }

    return $plaintext;
  }
  // #endregion
}
