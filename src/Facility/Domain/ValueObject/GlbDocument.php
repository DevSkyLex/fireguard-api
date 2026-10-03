<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Facility\Domain\Exception\FacilityModelException;
use JsonException;

use function array_is_list;
use function array_keys;
use function array_reverse;
use function base64_decode;
use function ceil;
use function count;
use function in_array;
use function is_array;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function mb_substr;
use function preg_match;
use function str_starts_with;
use function strlen;
use function substr;
use function unpack;

use const JSON_THROW_ON_ERROR;

/**
 * ValueObject GlbDocument. Bounded self-contained GLB 2.0 validation before storage.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GlbDocument
{
  // #region Constants
  /**
   * Constant MAX_BYTES.
   */
  public const int MAX_BYTES = 10 * 1024 * 1024;
  // #endregion

  // #region Constructor
  /**
   * Method __construct.
   *
   * Initializes the model capability with its typed dependencies and state.
   *
   * @access private
   * @since 1.0.0
   *
   * @param list<array{index: int, name: string}> $nodes
   *
   * @return void no return value
   */
  private function __construct(public array $nodes)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromContents.
   *
   * Validates bounded self-contained GLB content before it reaches durable storage.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $contents the contents
   *
   * @return self the operation result
   */
  public static function fromContents(string $contents): self
  {
    $size = strlen($contents);
    if ($size < 20 || $size > self::MAX_BYTES || 'glTF' !== substr($contents, 0, 4)) {
      throw FacilityModelException::invalid('A valid GLB 2.0 file of at most 10 MiB is required.');
    }
    /** @var array{version: int, length: int}|false $header */
    $header = unpack('Vversion/Vlength', substr($contents, 4, 8));
    if (false === $header || 2 !== $header['version'] || $size !== $header['length']) {
      throw FacilityModelException::invalid('The GLB version or declared length is invalid.');
    }
    $offset = 12;
    $json = null;
    $binaryLength = 0;
    $chunks = 0;
    while ($offset < $size) {
      if ($size - $offset < 8) {
        throw FacilityModelException::invalid('The GLB chunk header is truncated.');
      }
      /** @var array{length: int, type: int}|false $chunk */
      $chunk = unpack('Vlength/Vtype', substr($contents, $offset, 8));
      if (false === $chunk || 0 !== $chunk['length'] % 4 || $chunk['length'] > $size - $offset - 8) {
        throw FacilityModelException::invalid('The GLB chunk length is invalid.');
      }
      $offset += 8;
      if (0 === $chunks && 0x4E4F534A === $chunk['type']) {
        try {
          $json = json_decode(substr($contents, $offset, $chunk['length']), true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
          throw FacilityModelException::invalid('The GLB JSON is invalid.');
        }
      } elseif (1 === $chunks && 0x004E4942 === $chunk['type']) {
        $binaryLength = $chunk['length'];
      } else {
        throw FacilityModelException::invalid('The GLB must contain a JSON chunk and at most one embedded BIN chunk.');
      }
      $offset += $chunk['length'];
      ++$chunks;
    }
    if (!is_array($json) || !is_array($json['asset'] ?? null) || '2.0' !== ($json['asset']['version'] ?? null)
      || (isset($json['asset']['minVersion']) && '2.0' !== $json['asset']['minVersion'])) {
      throw FacilityModelException::invalid('The GLB asset must declare version 2.0.');
    }
    if ([] !== ($json['extensionsRequired'] ?? [])) {
      throw FacilityModelException::invalid('Required glTF extensions are not supported by this importer.');
    }
    self::validateUris($json);
    self::validateBinary($json, $binaryLength);
    $nodes = self::items($json, 'nodes');
    if ([] === $nodes || count($nodes) > 10000) {
      throw FacilityModelException::invalid('The GLB must contain between 1 and 10000 nodes.');
    }
    $meshes = self::items($json, 'meshes');
    $parents = [];
    foreach ($nodes as $index => $node) {
      if (isset($node['mesh'])) {
        self::index($node['mesh'], count($meshes));
      }
      $children = $node['children'] ?? [];
      if (!is_array($children) || !array_is_list($children)) {
        throw FacilityModelException::invalid('Node children must be a list.');
      }
      foreach ($children as $child) {
        self::index($child, count($nodes));
        if ($child === $index || isset($parents[$child])) {
          throw FacilityModelException::invalid('Node relationships must form a tree without shared children.');
        }
        $parents[$child] = $index;
      }
      foreach (['matrix' => 16, 'translation' => 3, 'rotation' => 4, 'scale' => 3] as $key => $length) {
        if (isset($node[$key])) {
          self::numbers($node[$key], $length);
        }
      }
      if (isset($node['matrix']) && (isset($node['translation']) || isset($node['rotation']) || isset($node['scale']))) {
        throw FacilityModelException::invalid('Node matrix and TRS transforms are mutually exclusive.');
      }
    }
    // Traverse each parent chain once; cap graph size and reject cycles before GLTFLoader recursion.
    $finished = [];
    foreach (array_keys($nodes) as $index) {
      $path = [];
      $cursor = $index;
      while (!isset($finished[$cursor])) {
        if (isset($path[$cursor])) {
          throw FacilityModelException::invalid('The GLB node graph contains a cycle.');
        }
        $path[$cursor] = true;
        if (count($path) > 256) {
          throw FacilityModelException::invalid('A GLB node hierarchy may be at most 256 levels deep.');
        }
        if (!isset($parents[$cursor])) {
          break;
        }
        $cursor = $parents[$cursor];
      }
      $depth = $finished[$cursor] ?? 0;
      foreach (array_reverse(array_keys($path)) as $visited) {
        ++$depth;
        if ($depth > 256) {
          throw FacilityModelException::invalid('A GLB node hierarchy may be at most 256 levels deep.');
        }
        $finished[$visited] = $depth;
      }
    }
    $scenes = self::items($json, 'scenes');
    if ([] === $scenes) {
      throw FacilityModelException::invalid('The GLB must contain a scene.');
    }
    if (isset($json['scene'])) {
      self::index($json['scene'], count($scenes));
    }
    foreach ($scenes as $scene) {
      if (!is_array($scene['nodes'] ?? null) || !array_is_list($scene['nodes'])) {
        throw FacilityModelException::invalid('Each scene must list its root nodes.');
      }
      $roots = [];
      foreach ($scene['nodes'] as $root) {
        self::index($root, count($nodes));
        if (isset($parents[$root]) || isset($roots[$root])) {
          throw FacilityModelException::invalid('Scene roots must be unique parentless nodes.');
        }
        $roots[$root] = true;
      }
    }
    self::validateReferences($json, count($nodes));
    $catalog = [];
    foreach ($nodes as $index => $node) {
      $name = $node['name'] ?? null;
      if (null !== $name && !is_string($name)) {
        throw FacilityModelException::invalid('A node name must be a string.');
      }
      $catalog[] = ['index' => $index, 'name' => is_string($name) && '' !== $name ? mb_substr($name, 0, 255) : 'Node ' . $index];
    }

    return new self($catalog);
  }

  /**
   * Method validateBinary.
   *
   * Checks binary buffer ranges and accessor sizes before loading the file.
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $json
   * @param int $binaryLength the binary length
   *
   * @return void no return value
   */
  private static function validateBinary(array $json, int $binaryLength): void
  {
    $buffers = self::items($json, 'buffers');
    if (count($buffers) > 1) {
      throw FacilityModelException::invalid('Only the embedded GLB buffer is supported.');
    }
    $length = 0;
    if ([] !== $buffers) {
      $buffer = $buffers[0];
      if (isset($buffer['uri']) || !is_int($buffer['byteLength'] ?? null)
        || $buffer['byteLength'] < 1 || $buffer['byteLength'] > $binaryLength || $binaryLength - $buffer['byteLength'] > 3) {
        throw FacilityModelException::invalid('The embedded GLB buffer length is invalid.');
      }
      $length = $buffer['byteLength'];
    } elseif (0 !== $binaryLength) {
      throw FacilityModelException::invalid('The GLB BIN chunk requires an embedded buffer declaration.');
    }
    $views = self::items($json, 'bufferViews');
    $normalizedViews = [];
    foreach ($views as $view) {
      if (0 !== ($view['buffer'] ?? null)) {
        throw FacilityModelException::invalid('A buffer view must reference the embedded buffer.');
      }
      $viewOffset = $view['byteOffset'] ?? 0;
      $viewLength = $view['byteLength'] ?? null;
      self::range($viewOffset, $viewLength, $length);
      $strideValue = $view['byteStride'] ?? null;
      if (null !== $strideValue && (!is_int($strideValue) || $strideValue < 4
        || $strideValue > 252 || 0 !== $strideValue % 4)) {
        throw FacilityModelException::invalid('A buffer view stride is invalid.');
      }
      $normalizedViews[] = ['byteLength' => $viewLength, 'byteStride' => $strideValue];
    }
    $views = $normalizedViews;
    $accessors = self::items($json, 'accessors');
    $decodedBytes = 0;
    foreach ($accessors as $accessor) {
      $bytes = match ($accessor['componentType'] ?? null) {
        5120, 5121 => 1, 5122, 5123 => 2, 5125, 5126 => 4,
        default => throw FacilityModelException::invalid('Unsupported accessor component type.'),
      };
      $components = match ($accessor['type'] ?? null) {
        'SCALAR' => 1, 'VEC2' => 2, 'VEC3' => 3, 'VEC4', 'MAT2' => 4, 'MAT3' => 9, 'MAT4' => 16,
        default => throw FacilityModelException::invalid('Unsupported accessor type.'),
      };
      if (!is_int($accessor['count'] ?? null) || $accessor['count'] < 1 || $accessor['count'] > 10000000) {
        throw FacilityModelException::invalid('An accessor count is invalid.');
      }
      $elementSize = $bytes * $components;
      // Matrices align each column to four bytes in the binary buffer.
      if ('MAT2' === $accessor['type']) {
        $elementSize = 2 * (int) (ceil(2 * $bytes / 4) * 4);
      } elseif ('MAT3' === $accessor['type']) {
        $elementSize = 3 * (int) (ceil(3 * $bytes / 4) * 4);
      }
      $decodedBytes += $accessor['count'] * $elementSize;
      if ($decodedBytes > 64 * 1024 * 1024) {
        throw FacilityModelException::invalid('Decoded GLB accessor data may not exceed 64 MiB.');
      }
      if (isset($accessor['bufferView'])) {
        self::index($accessor['bufferView'], count($views));
        $view = $views[$accessor['bufferView']];
        $stride = $view['byteStride'] ?? $elementSize;
        if ($stride < $elementSize) {
          throw FacilityModelException::invalid('An accessor stride is too short.');
        }
        $accessorOffset = $accessor['byteOffset'] ?? 0;
        self::range($accessorOffset, ($accessor['count'] - 1) * $stride + $elementSize, $view['byteLength']);
        if (0 !== $accessorOffset % $bytes) {
          throw FacilityModelException::invalid('An accessor offset is not aligned.');
        }
      } elseif (!isset($accessor['sparse'])) {
        throw FacilityModelException::invalid('An accessor requires a buffer view or sparse data.');
      }
      if (isset($accessor['sparse'])) {
        $sparse = $accessor['sparse'];
        if (!is_array($sparse) || !is_int($sparse['count'] ?? null) || $sparse['count'] < 1 || $sparse['count'] > $accessor['count']) {
          throw FacilityModelException::invalid('Sparse accessor count is invalid.');
        }
        foreach (['indices', 'values'] as $key) {
          $part = $sparse[$key] ?? null;
          if (!is_array($part)) {
            throw FacilityModelException::invalid('Sparse accessor data is invalid.');
          }
          self::index($part['bufferView'] ?? null, count($views));
          $partBytes = 'values' === $key ? $elementSize : match ($part['componentType'] ?? null) {
            5121 => 1, 5123 => 2, 5125 => 4,
            default => throw FacilityModelException::invalid('Sparse index type is invalid.'),
          };
          self::range($part['byteOffset'] ?? 0, $sparse['count'] * $partBytes, $views[$part['bufferView']]['byteLength']);
        }
      }
    }
    foreach (self::items($json, 'meshes') as $mesh) {
      if (!is_array($mesh['primitives'] ?? null) || [] === $mesh['primitives'] || !array_is_list($mesh['primitives'])) {
        throw FacilityModelException::invalid('Each mesh must contain primitives.');
      }
      foreach ($mesh['primitives'] as $primitive) {
        if (!is_array($primitive) || !is_array($primitive['attributes'] ?? null) || [] === $primitive['attributes']) {
          throw FacilityModelException::invalid('A primitive requires accessor attributes.');
        }
        foreach ($primitive['attributes'] as $attribute) {
          self::index($attribute, count($accessors));
        }
        if (isset($primitive['indices'])) {
          self::index($primitive['indices'], count($accessors));
        }
        if (isset($primitive['material'])) {
          self::index($primitive['material'], count(self::items($json, 'materials')));
        }
        if (isset($primitive['mode']) && (!is_int($primitive['mode']) || $primitive['mode'] < 0 || $primitive['mode'] > 6)) {
          throw FacilityModelException::invalid('A primitive rendering mode is invalid.');
        }
        $targets = $primitive['targets'] ?? [];
        if (!is_array($targets)) {
          throw FacilityModelException::invalid('Morph targets must be a list.');
        }
        foreach ($targets as $target) {
          if (!is_array($target)) {
            throw FacilityModelException::invalid('Morph targets must be objects.');
          }
          foreach ($target as $attribute) {
            self::index($attribute, count($accessors));
          }
        }
      }
    }
    foreach (self::items($json, 'images') as $image) {
      if (isset($image['bufferView'])) {
        self::index($image['bufferView'], count($views));
        if (!in_array($image['mimeType'] ?? null, ['image/png', 'image/jpeg', 'image/webp'], true) || isset($image['uri'])) {
          throw FacilityModelException::invalid('An embedded image MIME type is invalid.');
        }
      } elseif (!is_string($image['uri'] ?? null) || 1 !== preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#D', $image['uri'], $matches)
        || false === base64_decode($matches[2], true)) {
        throw FacilityModelException::invalid('GLB images must be embedded.');
      }
    }
  }

  /**
   * Method validateUris.
   *
   * Rejects external resource locations throughout the embedded GLB document.
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $value
   *
   * @return void no return value
   */
  private static function validateUris(array $value): void
  {
    foreach ($value as $key => $child) {
      if ('uri' === $key && (!is_string($child) || !str_starts_with($child, 'data:'))) {
        throw FacilityModelException::invalid('External GLB resources are forbidden.');
      }
      if (is_array($child)) {
        self::validateUris($child);
      }
    }
  }

  /**
   * Method validateReferences.
   *
   * Checks skin, texture and animation references against their collections.
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $json
   * @param int $nodeCount the node count
   *
   * @return void no return value
   */
  private static function validateReferences(array $json, int $nodeCount): void
  {
    $accessors = self::items($json, 'accessors');
    $skins = self::items($json, 'skins');
    $cameras = self::items($json, 'cameras');
    foreach (self::items($json, 'nodes') as $node) {
      if (isset($node['skin'])) {
        self::index($node['skin'], count($skins));
      }
      if (isset($node['camera'])) {
        self::index($node['camera'], count($cameras));
      }
    }
    foreach ($skins as $skin) {
      $joints = $skin['joints'] ?? null;
      if (!is_array($joints) || !array_is_list($joints) || [] === $joints) {
        throw FacilityModelException::invalid('A GLB skin must contain joint indices.');
      }
      $seen = [];
      foreach ($joints as $joint) {
        self::index($joint, $nodeCount);
        if (isset($seen[$joint])) {
          throw FacilityModelException::invalid('Skin joint indices must be unique.');
        }
        $seen[$joint] = true;
      }
      if (isset($skin['skeleton'])) {
        self::index($skin['skeleton'], $nodeCount);
      }
      if (isset($skin['inverseBindMatrices'])) {
        self::index($skin['inverseBindMatrices'], count($accessors));
      }
    }
    $images = self::items($json, 'images');
    $samplers = self::items($json, 'samplers');
    $textures = self::items($json, 'textures');
    foreach ($textures as $texture) {
      if (isset($texture['source'])) {
        self::index($texture['source'], count($images));
      }
      if (isset($texture['sampler'])) {
        self::index($texture['sampler'], count($samplers));
      }
    }
    foreach (self::items($json, 'materials') as $material) {
      $pbr = $material['pbrMetallicRoughness'] ?? [];
      if (!is_array($pbr)) {
        throw FacilityModelException::invalid('Material properties must be an object.');
      }
      foreach ([$material['normalTexture'] ?? null, $material['occlusionTexture'] ?? null,
        $material['emissiveTexture'] ?? null, $pbr['baseColorTexture'] ?? null, $pbr['metallicRoughnessTexture'] ?? null] as $textureInfo) {
        if (null !== $textureInfo) {
          if (!is_array($textureInfo)) {
            throw FacilityModelException::invalid('Material texture information must be an object.');
          }
          self::index($textureInfo['index'] ?? null, count($textures));
        }
      }
    }
    foreach (self::items($json, 'animations') as $animation) {
      $animationSamplers = self::items($animation, 'samplers');
      foreach ($animationSamplers as $sampler) {
        self::index($sampler['input'] ?? null, count($accessors));
        self::index($sampler['output'] ?? null, count($accessors));
      }
      foreach (self::items($animation, 'channels') as $channel) {
        self::index($channel['sampler'] ?? null, count($animationSamplers));
        $target = $channel['target'] ?? null;
        if (!is_array($target) || !in_array($target['path'] ?? null, ['translation', 'rotation', 'scale', 'weights'], true)) {
          throw FacilityModelException::invalid('Animation target properties are invalid.');
        }
        if (isset($target['node'])) {
          self::index($target['node'], $nodeCount);
        }
      }
    }
  }

  /**
   * Method items.
   *
   * Reads a validated list of embedded glTF objects.
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $json
   * @param string $key the key
   *
   * @return list<array<array-key, mixed>>
   */
  private static function items(array $json, string $key): array
  {
    $items = $json[$key] ?? [];
    if (!is_array($items) || !array_is_list($items)) {
      throw FacilityModelException::invalid('GLB ' . $key . ' must be a list.');
    }

    foreach ($items as $item) {
      if (!is_array($item)) {
        throw FacilityModelException::invalid('GLB ' . $key . ' entries must be objects.');
      }
    }

    /** @var list<array<array-key, mixed>> $items */
    return $items;
  }

  /**
   * Method index.
   *
   * Requires a valid index in the referenced glTF collection.
   *
   * @access private
   * @since 1.0.0
   *
   * @param mixed $index the index
   * @param int $count the count
   *
   * @return void no return value
   *
   * @phpstan-assert int $index
   */
  private static function index(mixed $index, int $count): void
  {
    if (!is_int($index) || $index < 0 || $index >= $count) {
      throw FacilityModelException::invalid('A GLB index is outside its referenced collection.');
    }
  }

  /**
   * Method range.
   *
   * Requires a positive bounded binary range.
   *
   * @access private
   * @since 1.0.0
   *
   * @param mixed $offset the offset
   * @param mixed $length the length
   * @param mixed $available the available
   *
   * @return void no return value
   *
   * @phpstan-assert int $offset
   * @phpstan-assert int $length
   * @phpstan-assert int $available
   */
  private static function range(mixed $offset, mixed $length, mixed $available): void
  {
    if (!is_int($offset) || !is_int($length) || !is_int($available) || $offset < 0 || $length < 1
      || $length > $available || $offset > $available - $length) {
      throw FacilityModelException::invalid('A GLB binary range is out of bounds.');
    }
  }

  /**
   * Method numbers.
   *
   * Requires the specified number of finite transform components.
   *
   * @access private
   * @since 1.0.0
   *
   * @param mixed $numbers the numbers
   * @param int $length the length
   *
   * @return void no return value
   */
  private static function numbers(mixed $numbers, int $length): void
  {
    if (!is_array($numbers) || !array_is_list($numbers) || count($numbers) !== $length) {
      throw FacilityModelException::invalid('A GLB transform has the wrong number of components.');
    }
    foreach ($numbers as $number) {
      if ((!is_int($number) && !is_float($number)) || !is_finite((float) $number)) {
        throw FacilityModelException::invalid('GLB transform components must be finite numbers.');
      }
    }
  }
  // #endregion
}
