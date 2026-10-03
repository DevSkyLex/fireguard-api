<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\Exception\FacilityModelException;
use Facility\Domain\ValueObject\GlbDocument;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Tests\Helper\GlbFixture;

use function array_replace;
use function pack;
use function str_repeat;
use function substr;
use function substr_replace;

/**
 * Test GlbDocumentTest.
 *
 * @category Unit Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GlbDocumentTest extends TestCase
{
  #[Test]
  public function readsARealSelfContainedTriangle(): void
  {
    self::assertSame([['index' => 0, 'name' => 'Building shell']], GlbDocument::fromContents(GlbFixture::contents())->nodes);
  }

  #[Test]
  #[DataProvider('invalidFiles')]
  public function rejectsMalformedAndUnsafeGlb(string $contents): void
  {
    $this->expectException(FacilityModelException::class);
    GlbDocument::fromContents($contents);
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function invalidFiles(): iterable
  {
    $valid = GlbFixture::contents();
    yield 'not binary glTF' => ['not-a-glb'];
    yield 'too large' => [str_repeat('x', GlbDocument::MAX_BYTES + 1)];
    yield 'bad magic' => ['FAKE' . substr($valid, 4)];
    yield 'old version' => [substr_replace($valid, pack('V', 1), 4, 4)];
    yield 'wrong declared length' => [$valid . "\0"];
    yield 'truncated chunk' => [substr_replace($valid, pack('V', 1000000), 12, 4)];
    yield 'unknown first chunk' => [substr_replace($valid, pack('V', 0), 16, 4)];
    yield 'invalid JSON' => [substr_replace($valid, '!', 20, 1)];
    $cases = [
      'asset version' => ['asset' => ['version' => '1.0']],
      'required compression' => ['extensionsRequired' => ['KHR_draco_mesh_compression']],
      'external buffer' => ['buffers' => [['byteLength' => 36, 'uri' => 'https://example.invalid/secret']]],
      'external image' => ['images' => [['uri' => '//example.invalid/a.png']]],
      'buffer declared too large' => ['buffers' => [['byteLength' => 37]]],
      'buffer view overrun' => ['bufferViews' => [['buffer' => 0, 'byteOffset' => 4, 'byteLength' => 36]]],
      'negative buffer offset' => ['bufferViews' => [['buffer' => 0, 'byteOffset' => -1, 'byteLength' => 36]]],
      'accessor overrun' => ['accessors' => [['bufferView' => 0, 'componentType' => 5126, 'count' => 4, 'type' => 'VEC3']]],
      'self cycle' => ['nodes' => [['children' => [0]]]],
      'mutual cycle' => ['nodes' => [['children' => [1]], ['children' => [0]]]],
      'child out of range' => ['nodes' => [['children' => [1]]]],
      'shared child' => ['nodes' => [['children' => [2]], ['children' => [2]], []]],
      'scene out of range' => ['scene' => 3],
      'scene root out of range' => ['scenes' => [['nodes' => [3]]]],
      'duplicate scene root' => ['scenes' => [['nodes' => [0, 0]]]],
      'scalar node' => ['nodes' => [42]],
      'bad transform' => ['nodes' => [['translation' => [0, 1]]]],
      'mesh index out of range' => ['nodes' => [['mesh' => 3]]],
      'accessor index out of range' => ['meshes' => [['primitives' => [['attributes' => ['POSITION' => 2]]]]]],
      'skin index out of range' => ['nodes' => [['skin' => 0]]],
      'camera index out of range' => ['nodes' => [['camera' => 0]]],
      'skin joint out of range' => ['skins' => [['joints' => [1]]]],
      'texture image out of range' => ['textures' => [['source' => 0]]],
      'material texture out of range' => ['materials' => [['normalTexture' => ['index' => 0]]]],
      'animation accessor out of range' => ['animations' => [['samplers' => [['input' => 1, 'output' => 0]], 'channels' => []]]],
    ];
    foreach ($cases as $label => $change) {
      yield $label => [GlbFixture::contents(array_replace(GlbFixture::document(), $change))];
    }
    $deep = GlbFixture::document();
    $deep['nodes'] = [];
    for ($index = 0; $index < 257; ++$index) {
      $deep['nodes'][] = $index < 256 ? ['children' => [$index + 1]] : [];
    }
    yield 'hierarchy depth, parent nodes ordered first' => [GlbFixture::contents($deep)];
    $hugeSparse = GlbFixture::document();
    $hugeSparse['accessors'] = [['componentType' => 5126, 'count' => 5000000, 'type' => 'VEC4',
      'sparse' => ['count' => 1, 'indices' => ['bufferView' => 0, 'componentType' => 5121], 'values' => ['bufferView' => 0]]]];
    yield 'sparse decoded allocation budget' => [GlbFixture::contents($hugeSparse)];
  }
}
