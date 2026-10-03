<?php

declare(strict_types=1);

namespace Tests\Helper;

use function json_encode;
use function pack;
use function str_repeat;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * Helper GlbFixture. Minimal real triangle file accepted by a glTF loader.
 *
 * @category Test Helper
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GlbFixture
{
  /**
   * @return array<string, mixed>
   */
  public static function document(): array
  {
    return [
      'asset' => ['version' => '2.0'], 'scene' => 0, 'scenes' => [['nodes' => [0]]],
      'nodes' => [['name' => 'Building shell', 'mesh' => 0]],
      'buffers' => [['byteLength' => 36]], 'bufferViews' => [['buffer' => 0, 'byteLength' => 36]],
      'accessors' => [['bufferView' => 0, 'componentType' => 5126, 'count' => 3, 'type' => 'VEC3', 'min' => [0, 0, 0], 'max' => [1, 1, 0]]],
      'meshes' => [['primitives' => [['attributes' => ['POSITION' => 0]]]]],
    ];
  }

  /**
   * @param ?array<string, mixed> $document
   */
  public static function contents(?array $document = null, ?string $binary = null): string
  {
    $json = json_encode($document ?? self::document(), JSON_THROW_ON_ERROR);
    $json .= str_repeat(' ', (4 - strlen($json) % 4) % 4);
    $binary ??= pack('g*', 0, 0, 0, 1, 0, 0, 0, 1, 0);
    $binary .= str_repeat("\0", (4 - strlen($binary) % 4) % 4);

    return 'glTF' . pack('VV', 2, 12 + 8 + strlen($json) + 8 + strlen($binary))
      . pack('VV', strlen($json), 0x4E4F534A) . $json . pack('VV', strlen($binary), 0x004E4942) . $binary;
  }
}
