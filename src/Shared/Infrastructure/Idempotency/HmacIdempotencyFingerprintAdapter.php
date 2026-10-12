<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use InvalidArgumentException;
use Shared\Application\Port\Outbound\Idempotency\IdempotencyFingerprintPort;

use function array_is_list;
use function base64_decode;
use function hash_hmac;
use function is_array;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function json_encode;
use function ksort;
use function strlen;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const SORT_STRING;

/**
 * Class HmacIdempotencyFingerprintAdapter
 *
 * Hashes canonical input without persisting raw keys, credentials or input bytes.
 *
 * @category Adapter
 */
final readonly class HmacIdempotencyFingerprintAdapter implements IdempotencyFingerprintPort
{
  // #region Properties
  /**
   * Property key
   */
  private string $key;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $base64Key dedicated random HMAC key, at least 32 decoded bytes
   *
   * @return void
   */
  public function __construct(string $base64Key)
  {
    $key = base64_decode($base64Key, true);
    if (false === $key || strlen($key) < 32) {
      throw new InvalidArgumentException('The dedicated idempotency HMAC key is invalid.');
    }
    $this->key = $key;
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function digest(string $purpose, mixed $value): string
  {
    $canonical = json_encode($this->canonicalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    if (strlen($canonical) > 8388608) {
      throw new InvalidArgumentException('Canonical idempotency input exceeds its byte budget.');
    }

    return hash_hmac('sha256', 'fireguard:idempotency:v1:' . $purpose . "\0" . $canonical, $this->key);
  }

  /**
   * Method canonicalize
   *
   * Preserves list order and scalar types while sorting map keys deterministically.
   *
   * @access private
   *
   * @param mixed $value input data
   * @param int $depth recursive nesting depth
   *
   * @return mixed canonical data
   */
  private function canonicalize(mixed $value, int $depth = 0): mixed
  {
    if ($depth > 64) {
      throw new InvalidArgumentException('Canonical idempotency input is too deeply nested.');
    }
    if (is_array($value)) {
      if (!array_is_list($value)) {
        ksort($value, SORT_STRING);
      }
      foreach ($value as $key => $item) {
        $value[$key] = $this->canonicalize($item, $depth + 1);
      }

      return $value;
    }
    if (null === $value || is_string($value) || is_int($value) || is_bool($value) || (is_float($value) && is_finite($value))) {
      return $value;
    }

    throw new InvalidArgumentException('Canonical idempotency input must contain finite scalar data.');
  }
  // #endregion
}
