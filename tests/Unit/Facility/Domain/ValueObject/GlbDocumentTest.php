<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\Exception\FacilityModelException;
use Facility\Domain\ValueObject\{GlbBinaryValidation, GlbDocument, GlbNodeValidation, GlbReferenceValidation, GlbValueValidation};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Tests\Helper\GlbFixture;

use function array_replace;
use function array_reverse;
use function json_encode;
use function pack;
use function str_repeat;
use function strlen;
use function substr;
use function substr_replace;

use const JSON_THROW_ON_ERROR;

/**
 * Test GlbDocumentTest.
 *
 * @category Unit Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GlbDocument::class)]
#[CoversClass(GlbBinaryValidation::class)]
#[CoversClass(GlbNodeValidation::class)]
#[CoversClass(GlbReferenceValidation::class)]
#[CoversClass(GlbValueValidation::class)]
final class GlbDocumentTest extends TestCase
{
  #[Test]
  public function readsARealSelfContainedTriangle(): void
  {
    self::assertSame([['index' => 0, 'name' => 'Building shell']], GlbDocument::fromContents(GlbFixture::contents())->nodes);
  }

  /**
   * Method acceptsEmbeddedSparseMatricesAndCrossCollectionReferences
   *
   * Preserves supported sparse and aligned matrix storage alongside skin, material and animation links.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function acceptsEmbeddedSparseMatricesAndCrossCollectionReferences(): void
  {
    $document = GlbFixture::document();
    self::assertIsArray($document['accessors']);
    $document['nodes'] = [
      ['name' => 'Shell', 'mesh' => 0, 'children' => [1], 'skin' => 0, 'translation' => [0, 0, 0]],
      ['name' => 'Joint', 'camera' => 0, 'rotation' => [0, 0, 0, 1], 'scale' => [1, 1, 1]],
    ];
    $document['accessors'][] = ['componentType' => 5121, 'count' => 3, 'type' => 'MAT2',
      'sparse' => ['count' => 1, 'indices' => ['bufferView' => 0, 'componentType' => 5121], 'values' => ['bufferView' => 0]]];
    $document['accessors'][] = ['bufferView' => 0, 'componentType' => 5121, 'count' => 3, 'type' => 'MAT3'];
    $document['skins'] = [['joints' => [1], 'skeleton' => 0, 'inverseBindMatrices' => 0]];
    $document['cameras'] = [['type' => 'perspective', 'perspective' => ['yfov' => 1, 'znear' => 0.1]]];
    $document['images'] = [['uri' => 'data:image/png;base64,AA==']];
    $document['samplers'] = [[]];
    $document['textures'] = [['source' => 0, 'sampler' => 0]];
    $document['materials'] = [['normalTexture' => ['index' => 0], 'occlusionTexture' => ['index' => 0],
      'emissiveTexture' => ['index' => 0], 'pbrMetallicRoughness' => ['baseColorTexture' => ['index' => 0], 'metallicRoughnessTexture' => ['index' => 0]]], []];
    $document['animations'] = [['samplers' => [['input' => 0, 'output' => 0]],
      'channels' => [['sampler' => 0, 'target' => ['node' => 1, 'path' => 'translation']]]]];
    $document['meshes'] = [['primitives' => [['attributes' => ['POSITION' => 0],
      'indices' => 0, 'material' => 0, 'targets' => [['POSITION' => 0]]]]]];

    self::assertSame(
      [['index' => 0, 'name' => 'Shell'], ['index' => 1, 'name' => 'Joint']],
      GlbDocument::fromContents(GlbFixture::contents($document))->nodes,
    );
  }

  /**
   * Method acceptsEveryCorePrimitiveModeAndEmbeddedImages
   *
   * Keeps all seven core render modes and embedded image references valid after decomposition.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function acceptsEveryCorePrimitiveModeAndEmbeddedImages(): void
  {
    $document = GlbFixture::document();
    $document['bufferViews'] = [['buffer' => 0, 'byteLength' => 36, 'byteStride' => 12]];
    $document['images'] = [['bufferView' => 0, 'mimeType' => 'image/png']];
    $document['nodes'] = [['name' => 'Building shell', 'mesh' => 0,
      'matrix' => [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1]]];
    for ($mode = 0; $mode <= 6; ++$mode) {
      $document['meshes'] = [['primitives' => [['attributes' => ['POSITION' => 0], 'mode' => $mode]]]];
      self::assertSame('Building shell', GlbDocument::fromContents(GlbFixture::contents($document))->nodes[0]['name']);
    }
  }

  /**
   * Method acceptsMaximumDepthRegardlessOfParentOrdering
   *
   * Verifies the depth boundary with either direction of persisted node ordering.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function acceptsMaximumDepthRegardlessOfParentOrdering(): void
  {
    $document = GlbFixture::document();
    $document['nodes'] = [];
    for ($index = 0; $index < 256; ++$index) {
      $document['nodes'][] = $index < 255 ? ['children' => [$index + 1]] : [];
    }
    self::assertCount(256, GlbDocument::fromContents(GlbFixture::contents($document))->nodes);
    $document['nodes'] = array_reverse($document['nodes']);
    foreach ($document['nodes'] as $index => &$node) {
      $node = $index > 0 ? ['children' => [$index - 1]] : [];
    }
    unset($node);
    $document['scenes'] = [['nodes' => [255]]];
    self::assertCount(256, GlbDocument::fromContents(GlbFixture::contents($document))->nodes);
  }

  /**
   * Method acceptsJsonOnlySceneWithoutBinaryResources
   *
   * Keeps a valid self-contained GLB scene without a BIN chunk importable.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function acceptsJsonOnlySceneWithoutBinaryResources(): void
  {
    $document = ['asset' => ['version' => '2.0'], 'nodes' => [[]], 'scenes' => [['nodes' => [0]]]];
    $json = json_encode($document, JSON_THROW_ON_ERROR);
    $json .= str_repeat(' ', (4 - strlen($json) % 4) % 4);
    $contents = 'glTF' . pack('VV', 2, 20 + strlen($json)) . pack('VV', strlen($json), 0x4E4F534A) . $json;
    self::assertSame([['index' => 0, 'name' => 'Node 0']], GlbDocument::fromContents($contents)->nodes);
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
    yield 'scalar JSON document' => ['glTF' . pack('VV', 2, 24) . pack('VV', 4, 0x4E4F534A) . '1   '];
    yield 'truncated trailing chunk header' => [substr_replace($valid, pack('V', strlen($valid) + 4), 8, 4) . '0000'];
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
      'asset minimum version' => ['asset' => ['version' => '2.0', 'minVersion' => '2.1']],
      'scalar accessor collection' => ['accessors' => 1],
      'multiple embedded buffers' => ['buffers' => [['byteLength' => 36], ['byteLength' => 36]]],
      'undeclared binary buffer' => ['buffers' => []],
      'wrong buffer index' => ['bufferViews' => [['buffer' => 1, 'byteLength' => 36]]],
      'unaligned stride' => ['bufferViews' => [['buffer' => 0, 'byteLength' => 36, 'byteStride' => 5]]],
      'short accessor stride' => ['bufferViews' => [['buffer' => 0, 'byteLength' => 36, 'byteStride' => 4]]],
      'unsupported component type' => ['accessors' => [['bufferView' => 0, 'componentType' => 0, 'count' => 3, 'type' => 'VEC3']]],
      'unsupported accessor shape' => ['accessors' => [['bufferView' => 0, 'componentType' => 5126, 'count' => 3, 'type' => 'UNKNOWN']]],
      'zero accessor count' => ['accessors' => [['bufferView' => 0, 'componentType' => 5126, 'count' => 0, 'type' => 'VEC3']]],
      'unaligned accessor offset' => ['accessors' => [['bufferView' => 0, 'componentType' => 5126, 'count' => 1, 'type' => 'VEC3', 'byteOffset' => 1]]],
      'missing accessor storage' => ['accessors' => [['componentType' => 5126, 'count' => 1, 'type' => 'VEC3']]],
      'invalid sparse count' => ['accessors' => [['componentType' => 5126, 'count' => 1, 'type' => 'VEC3', 'sparse' => ['count' => 2]]]],
      'missing sparse part' => ['accessors' => [['componentType' => 5126, 'count' => 1, 'type' => 'VEC3', 'sparse' => ['count' => 1]]]],
      'invalid sparse index encoding' => ['accessors' => [['componentType' => 5126, 'count' => 1, 'type' => 'VEC3',
        'sparse' => ['count' => 1, 'indices' => ['bufferView' => 0, 'componentType' => 5126], 'values' => ['bufferView' => 0]]]]],
      'empty mesh primitives' => ['meshes' => [['primitives' => []]]],
      'empty primitive attributes' => ['meshes' => [['primitives' => [['attributes' => []]]]]],
      'invalid primitive mode' => ['meshes' => [['primitives' => [['attributes' => ['POSITION' => 0], 'mode' => 7]]]]],
      'invalid morph target collection' => ['meshes' => [['primitives' => [['attributes' => ['POSITION' => 0], 'targets' => 1]]]]],
      'invalid morph target object' => ['meshes' => [['primitives' => [['attributes' => ['POSITION' => 0], 'targets' => [1]]]]]],
      'invalid embedded image mime' => ['images' => [['bufferView' => 0, 'mimeType' => 'text/plain']]],
      'invalid data image encoding' => ['images' => [['uri' => 'data:image/png;base64,invalid===']]],
      'empty node collection' => ['nodes' => []],
      'invalid child collection' => ['nodes' => [['children' => 1]]],
      'matrix and translation' => ['nodes' => [['matrix' => [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1], 'translation' => [0, 0, 0]]]],
      'nonnumeric transform' => ['nodes' => [['translation' => ['x', 0, 0]]]],
      'invalid node name' => ['nodes' => [['name' => 42]]],
      'missing scenes' => ['scenes' => []],
      'invalid scene roots collection' => ['scenes' => [['nodes' => 1]]],
      'parented scene root' => ['nodes' => [['children' => [1]], []], 'scenes' => [['nodes' => [1]]]],
      'empty skin joints' => ['skins' => [['joints' => []]]],
      'duplicate skin joints' => ['skins' => [['joints' => [0, 0]]]],
      'texture sampler out of range' => ['textures' => [['sampler' => 0]]],
      'invalid material properties' => ['materials' => [['pbrMetallicRoughness' => 1]]],
      'invalid material texture object' => ['materials' => [['normalTexture' => 1]]],
      'animation sampler out of range' => ['animations' => [['samplers' => [], 'channels' => [['sampler' => 0]]]]],
      'invalid animation target path' => ['animations' => [['samplers' => [['input' => 0, 'output' => 0]],
        'channels' => [['sampler' => 0, 'target' => ['node' => 0, 'path' => 'unknown']]]]]],
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
