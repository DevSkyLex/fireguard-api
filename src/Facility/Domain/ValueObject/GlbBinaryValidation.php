<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Facility\Domain\Exception\FacilityModelException;

use function array_is_list;
use function base64_decode;
use function ceil;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;

/**
 * Class GlbBinaryValidation
 *
 * Validates embedded buffers and decoded allocation limits before checking renderable primitives.
 *
 * @category ValueObject
 */
final readonly class GlbBinaryValidation
{
  // #region Methods
  /**
   * Method validate
   *
   * Checks binary ranges, accessors, mesh primitives and embedded image sources.
   *
   * @access public
   *
   * @param array<array-key, mixed> $json the decoded GLB document
   * @param int $binaryLength the padded embedded BIN chunk length in bytes
   *
   * @return void
   */
  public static function validate(array $json, int $binaryLength): void
  {
    $length = self::embeddedBufferLength($json, $binaryLength);
    $views = self::bufferViews($json, $length);
    $accessors = GlbValueValidation::items($json, 'accessors');
    self::validateAccessors($accessors, $views);
    self::validateMeshes($json, count($accessors));
    self::validateImages($json, count($views));
  }

  /**
   * Method embeddedBufferLength
   *
   * Requires at most one URI-free embedded buffer and permits only GLB alignment padding.
   *
   * @access private
   *
   * @param array<array-key, mixed> $json the decoded document
   * @param int $binaryLength the padded BIN length in bytes
   *
   * @return int the declared buffer length in bytes, or zero when no buffer exists
   */
  private static function embeddedBufferLength(array $json, int $binaryLength): int
  {
    $buffers = GlbValueValidation::items($json, 'buffers');
    if (count($buffers) > 1) {
      throw FacilityModelException::invalid('Only the embedded GLB buffer is supported.');
    }
    if ([] === $buffers) {
      if (0 !== $binaryLength) {
        throw FacilityModelException::invalid('The GLB BIN chunk requires an embedded buffer declaration.');
      }

      return 0;
    }
    $buffer = $buffers[0];
    if (isset($buffer['uri']) || !is_int($buffer['byteLength'] ?? null)
      || $buffer['byteLength'] < 1 || $buffer['byteLength'] > $binaryLength || $binaryLength - $buffer['byteLength'] > 3) {
      throw FacilityModelException::invalid('The embedded GLB buffer length is invalid.');
    }

    return $buffer['byteLength'];
  }

  /**
   * Method bufferViews
   *
   * Normalizes bounded buffer ranges and optional aligned strides.
   *
   * @access private
   *
   * @param array<array-key, mixed> $json the decoded document
   * @param int $length the declared buffer length in bytes
   *
   * @return list<array{byteLength: int, byteStride: ?int}> validated buffer views
   */
  private static function bufferViews(array $json, int $length): array
  {
    $views = [];
    foreach (GlbValueValidation::items($json, 'bufferViews') as $view) {
      if (0 !== ($view['buffer'] ?? null)) {
        throw FacilityModelException::invalid('A buffer view must reference the embedded buffer.');
      }
      $offset = $view['byteOffset'] ?? 0;
      $viewLength = $view['byteLength'] ?? null;
      GlbValueValidation::range($offset, $viewLength, $length);
      $stride = $view['byteStride'] ?? null;
      if (null !== $stride && (!is_int($stride) || $stride < 4 || $stride > 252 || 0 !== $stride % 4)) {
        throw FacilityModelException::invalid('A buffer view stride is invalid.');
      }
      $views[] = ['byteLength' => $viewLength, 'byteStride' => $stride];
    }

    return $views;
  }

  /**
   * Method validateAccessors
   *
   * Bounds decoded accessor allocation and validates dense or sparse binary storage.
   *
   * @access private
   *
   * @param list<array<array-key, mixed>> $accessors the decoded accessor collection
   * @param list<array{byteLength: int, byteStride: ?int}> $views validated buffer views
   *
   * @return void
   */
  private static function validateAccessors(array $accessors, array $views): void
  {
    $decodedBytes = 0;
    foreach ($accessors as $accessor) {
      $bytes = self::componentBytes($accessor['componentType'] ?? null);
      $elementSize = self::elementSize($accessor['type'] ?? null, $bytes);
      if (!is_int($accessor['count'] ?? null) || $accessor['count'] < 1 || $accessor['count'] > 10000000) {
        throw FacilityModelException::invalid('An accessor count is invalid.');
      }
      $decodedBytes += $accessor['count'] * $elementSize;
      if ($decodedBytes > 64 * 1024 * 1024) {
        throw FacilityModelException::invalid('Decoded GLB accessor data may not exceed 64 MiB.');
      }
      self::validateAccessorStorage($accessor, $views, $bytes, $elementSize, $accessor['count']);
    }
  }

  /**
   * Method componentBytes
   *
   * Resolves supported glTF scalar component types without accepting unknown encodings.
   *
   * @access private
   *
   * @param mixed $type the untrusted component type
   *
   * @return int byte length of one scalar component
   */
  private static function componentBytes(mixed $type): int
  {
    return match ($type) {
      5120, 5121 => 1, 5122, 5123 => 2, 5125, 5126 => 4,
      default => throw FacilityModelException::invalid('Unsupported accessor component type.'),
    };
  }

  /**
   * Method elementSize
   *
   * Accounts for four-byte column alignment in small matrix component encodings.
   *
   * @access private
   *
   * @param mixed $type the untrusted accessor shape
   * @param int $bytes the scalar component size in bytes
   *
   * @return int the aligned byte size of one accessor element
   */
  private static function elementSize(mixed $type, int $bytes): int
  {
    $components = match ($type) {
      'SCALAR' => 1, 'VEC2' => 2, 'VEC3' => 3, 'VEC4', 'MAT2' => 4, 'MAT3' => 9, 'MAT4' => 16,
      default => throw FacilityModelException::invalid('Unsupported accessor type.'),
    };
    if ('MAT2' === $type) {
      return 2 * (int) (ceil(2 * $bytes / 4) * 4);
    }
    if ('MAT3' === $type) {
      return 3 * (int) (ceil(3 * $bytes / 4) * 4);
    }

    return $bytes * $components;
  }

  /**
   * Method validateAccessorStorage
   *
   * Checks dense accessor strides and alignment, then any additional sparse storage.
   *
   * @access private
   *
   * @param array<array-key, mixed> $accessor the untrusted accessor storage fields
   * @param list<array{byteLength: int, byteStride: ?int}> $views validated buffer views
   * @param int $bytes the scalar component size in bytes
   * @param int $elementSize the aligned element size in bytes
   * @param int $count the validated accessor element count
   *
   * @return void
   */
  private static function validateAccessorStorage(array $accessor, array $views, int $bytes, int $elementSize, int $count): void
  {
    if (isset($accessor['bufferView'])) {
      GlbValueValidation::index($accessor['bufferView'], count($views));
      $view = $views[$accessor['bufferView']];
      $stride = $view['byteStride'] ?? $elementSize;
      if ($stride < $elementSize) {
        throw FacilityModelException::invalid('An accessor stride is too short.');
      }
      $offset = $accessor['byteOffset'] ?? 0;
      GlbValueValidation::range($offset, ($count - 1) * $stride + $elementSize, $view['byteLength']);
      if (0 !== $offset % $bytes) {
        throw FacilityModelException::invalid('An accessor offset is not aligned.');
      }
    } elseif (!isset($accessor['sparse'])) {
      throw FacilityModelException::invalid('An accessor requires a buffer view or sparse data.');
    }
    if (isset($accessor['sparse'])) {
      self::validateSparse($accessor['sparse'], $count, $views, $elementSize);
    }
  }

  /**
   * Method validateSparse
   *
   * Bounds sparse entry counts by their parent accessor before checking both binary parts.
   *
   * @access private
   *
   * @param mixed $sparse the untrusted sparse definition
   * @param int $accessorCount the total accessor element count
   * @param list<array{byteLength: int, byteStride: ?int}> $views validated buffer views
   * @param int $elementSize the aligned accessor element size in bytes
   *
   * @return void
   */
  private static function validateSparse(mixed $sparse, int $accessorCount, array $views, int $elementSize): void
  {
    if (!is_array($sparse) || !is_int($sparse['count'] ?? null) || $sparse['count'] < 1 || $sparse['count'] > $accessorCount) {
      throw FacilityModelException::invalid('Sparse accessor count is invalid.');
    }
    foreach (['indices', 'values'] as $key) {
      self::validateSparsePart($sparse[$key] ?? null, $key, $sparse['count'], $views, $elementSize);
    }
  }

  /**
   * Method validateSparsePart
   *
   * Checks the referenced range and supported unsigned encoding for a sparse binary part.
   *
   * @access private
   *
   * @param mixed $part the untrusted sparse binary part
   * @param string $key indices or values
   * @param int $count the validated sparse entry count
   * @param list<array{byteLength: int, byteStride: ?int}> $views validated buffer views
   * @param int $elementSize the aligned accessor element size in bytes
   *
   * @return void
   */
  private static function validateSparsePart(mixed $part, string $key, int $count, array $views, int $elementSize): void
  {
    if (!is_array($part)) {
      throw FacilityModelException::invalid('Sparse accessor data is invalid.');
    }
    GlbValueValidation::index($part['bufferView'] ?? null, count($views));
    $partBytes = 'values' === $key ? $elementSize : match ($part['componentType'] ?? null) {
      5121 => 1, 5123 => 2, 5125 => 4,
      default => throw FacilityModelException::invalid('Sparse index type is invalid.'),
    };
    GlbValueValidation::range($part['byteOffset'] ?? 0, $count * $partBytes, $views[$part['bufferView']]['byteLength']);
  }

  /**
   * Method validateMeshes
   *
   * Requires a nonempty primitive collection on every mesh.
   *
   * @access private
   *
   * @param array<array-key, mixed> $json the decoded document
   * @param int $accessorCount the number of available accessors
   *
   * @return void
   */
  private static function validateMeshes(array $json, int $accessorCount): void
  {
    $materialCount = count(GlbValueValidation::items($json, 'materials'));
    foreach (GlbValueValidation::items($json, 'meshes') as $mesh) {
      if (!is_array($mesh['primitives'] ?? null) || [] === $mesh['primitives'] || !array_is_list($mesh['primitives'])) {
        throw FacilityModelException::invalid('Each mesh must contain primitives.');
      }
      foreach ($mesh['primitives'] as $primitive) {
        self::validatePrimitive($primitive, $accessorCount, $materialCount);
      }
    }
  }

  /**
   * Method validatePrimitive
   *
   * Validates geometry attributes and mode, including point and line primitives.
   *
   * @access private
   *
   * @param mixed $primitive the untrusted primitive
   * @param int $accessorCount the number of available accessors
   * @param int $materialCount the number of available materials
   *
   * @return void
   */
  private static function validatePrimitive(mixed $primitive, int $accessorCount, int $materialCount): void
  {
    if (!is_array($primitive) || !is_array($primitive['attributes'] ?? null) || [] === $primitive['attributes']) {
      throw FacilityModelException::invalid('A primitive requires accessor attributes.');
    }
    foreach ($primitive['attributes'] as $attribute) {
      GlbValueValidation::index($attribute, $accessorCount);
    }
    foreach (['indices' => $accessorCount, 'material' => $materialCount] as $key => $count) {
      if (isset($primitive[$key])) {
        GlbValueValidation::index($primitive[$key], $count);
      }
    }
    if (isset($primitive['mode']) && (!is_int($primitive['mode']) || $primitive['mode'] < 0 || $primitive['mode'] > 6)) {
      throw FacilityModelException::invalid('A primitive rendering mode is invalid.');
    }
    self::validateMorphTargets($primitive['targets'] ?? [], $accessorCount);
  }

  /**
   * Method validateMorphTargets
   *
   * Checks every morph target attribute against the same accessor collection.
   *
   * @access private
   *
   * @param mixed $targets the untrusted morph targets
   * @param int $accessorCount the number of available accessors
   *
   * @return void
   */
  private static function validateMorphTargets(mixed $targets, int $accessorCount): void
  {
    if (!is_array($targets)) {
      throw FacilityModelException::invalid('Morph targets must be a list.');
    }
    foreach ($targets as $target) {
      if (!is_array($target)) {
        throw FacilityModelException::invalid('Morph targets must be objects.');
      }
      foreach ($target as $attribute) {
        GlbValueValidation::index($attribute, $accessorCount);
      }
    }
  }

  /**
   * Method validateImages
   *
   * Accepts supported embedded buffer images or strictly decoded base64 image data URIs.
   *
   * @access private
   *
   * @param array<array-key, mixed> $json the decoded document
   * @param int $viewCount the number of validated buffer views
   *
   * @return void
   */
  private static function validateImages(array $json, int $viewCount): void
  {
    foreach (GlbValueValidation::items($json, 'images') as $image) {
      if (isset($image['bufferView'])) {
        GlbValueValidation::index($image['bufferView'], $viewCount);
        if (!in_array($image['mimeType'] ?? null, ['image/png', 'image/jpeg', 'image/webp'], true) || isset($image['uri'])) {
          throw FacilityModelException::invalid('An embedded image MIME type is invalid.');
        }
      } elseif (!is_string($image['uri'] ?? null) || 1 !== preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#D', $image['uri'], $matches)
        || false === base64_decode($matches[2], true)) {
        throw FacilityModelException::invalid('GLB images must be embedded.');
      }
    }
  }
  // #endregion
}
