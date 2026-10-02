<?php

declare(strict_types=1);

namespace Tests\Support\GeoIp;

use DateTimeImmutable;
use InvalidArgumentException;

use function array_is_list;
use function chr;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function pack;
use function str_repeat;
use function strlen;
use function substr;

/**
 * Tiny synthetic MMDB: every address resolves to test data, never real provider records.
 *
 * @category Test Support
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SyntheticMmdb
{
  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param DateTimeImmutable $build synthetic build time
   * @param array<string, mixed> $record fake country/city record
   * @param bool $mapped whether the synthetic tree contains a record
   *
   * @return string complete tiny database bytes
   */
  public static function bytes(DateTimeImmutable $build, array $record = ['country' => ['iso_code' => 'FR'], 'city' => ['names' => ['en' => 'Paris']]], bool $mapped = true): string
  {
    // A single 24-bit node: record 17 points past the tree and its 16-byte separator.
    $pointer = substr(pack('N', $mapped ? 17 : 1), 1);
    $metadata = [
      'binary_format_major_version' => 2,
      'binary_format_minor_version' => 0,
      'build_epoch' => $build->getTimestamp(),
      'database_type' => 'DBIP-City-Lite',
      'description' => ['en' => 'Synthetic Fireguard test data'],
      'ip_version' => 6,
      'languages' => ['en'],
      'node_count' => 1,
      'record_size' => 24,
    ];

    return $pointer . $pointer . str_repeat("\0", 16) . self::encode($record) . "\xab\xcd\xefMaxMind.com" . self::encode($metadata);
  }

  /**
   * @since 1.0.0
   *
   * @param mixed $value small scalar/map/list used by the fixture
   *
   * @return string encoded MMDB value
   */
  private static function encode(mixed $value): string
  {
    if (is_array($value)) {
      $list = array_is_list($value);
      $bytes = self::header($list ? 11 : 7, count($value));
      foreach ($value as $key => $child) {
        if (is_string($key)) {
          $bytes .= self::encode($key);
        }
        $bytes .= self::encode($child);
      }

      return $bytes;
    }
    if (is_int($value)) {
      return self::header(6, 4) . pack('N', $value);
    }

    if (!is_string($value)) {
      throw new InvalidArgumentException('Unsupported synthetic MMDB value.');
    }
    $string = $value;

    return self::header(2, strlen($string)) . $string;
  }

  /**
   * @since 1.0.0
   *
   * @param int $type MMDB type identifier
   * @param int $size payload bytes or item count, limited to these small fixtures
   *
   * @return string MMDB control bytes
   */
  private static function header(int $type, int $size): string
  {
    $control = (($type < 8 ? $type : 0) << 5) | ($size < 29 ? $size : 29);

    return chr($control) . ($type < 8 ? '' : chr($type - 7)) . ($size < 29 ? '' : chr($size - 29));
  }
  // #endregion
}
