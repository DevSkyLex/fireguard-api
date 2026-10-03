<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Facility\Domain\Exception\FacilityModelException;
use JsonException;

use function count;
use function is_array;
use function is_string;
use function json_decode;
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
   * Retains the immutable binding catalog only after the complete file has been validated.
   *
   * @access private
   * @since 1.0.0
   *
   * @param list<array{index: int, name: string}> $nodes the stable node binding catalog
   *
   * @return void
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
   * @param string $contents the immutable uploaded file bytes
   *
   * @return self the validated node catalog
   */
  public static function fromContents(string $contents): self
  {
    $size = self::validateHeader($contents);
    [$json, $binaryLength] = self::readChunks($contents, $size);
    self::validateAsset($json);
    self::validateUris($json);
    GlbBinaryValidation::validate($json, $binaryLength);
    $nodes = GlbNodeValidation::catalog($json);
    GlbReferenceValidation::validate($json, count($nodes));

    return new self($nodes);
  }

  /**
   * Method validateHeader
   *
   * Enforces the upload byte limit and exact GLB 2.0 header length.
   *
   * @access private
   *
   * @param string $contents the uploaded file bytes
   *
   * @return int the validated complete file length in bytes
   */
  private static function validateHeader(string $contents): int
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

    return $size;
  }

  /**
   * Method readChunks
   *
   * Requires one leading JSON chunk and at most one embedded binary chunk, with exact bounds.
   *
   * @access private
   *
   * @param string $contents the uploaded file bytes
   * @param int $size the validated complete file length in bytes
   *
   * @return array{array<array-key, mixed>, int} decoded JSON and embedded binary chunk length
   */
  private static function readChunks(string $contents, int $size): array
  {
    $offset = 12;
    $json = [];
    $binaryLength = 0;
    $chunks = 0;
    while ($offset < $size) {
      $chunk = self::chunkHeader($contents, $offset, $size);
      $offset += 8;
      if (0 === $chunks && 0x4E4F534A === $chunk['type']) {
        $json = self::decodeJson(substr($contents, $offset, $chunk['length']));
      } elseif (1 === $chunks && 0x004E4942 === $chunk['type']) {
        $binaryLength = $chunk['length'];
      } else {
        throw FacilityModelException::invalid('The GLB must contain a JSON chunk and at most one embedded BIN chunk.');
      }
      $offset += $chunk['length'];
      ++$chunks;
    }

    return [$json, $binaryLength];
  }

  /**
   * Method chunkHeader
   *
   * Rejects truncated, unaligned or out-of-bounds chunk lengths before decoding contents.
   *
   * @access private
   *
   * @param string $contents the uploaded file bytes
   * @param int $offset the current chunk header byte offset
   * @param int $size the complete file length in bytes
   *
   * @return array{length: int, type: int} validated chunk header
   */
  private static function chunkHeader(string $contents, int $offset, int $size): array
  {
    if ($size - $offset < 8) {
      throw FacilityModelException::invalid('The GLB chunk header is truncated.');
    }
    /** @var array{length: int, type: int}|false $chunk */
    $chunk = unpack('Vlength/Vtype', substr($contents, $offset, 8));
    if (false === $chunk || 0 !== $chunk['length'] % 4 || $chunk['length'] > $size - $offset - 8) {
      throw FacilityModelException::invalid('The GLB chunk length is invalid.');
    }

    return $chunk;
  }

  /**
   * Method decodeJson
   *
   * Applies a bounded decoding depth and rejects scalar or malformed JSON documents.
   *
   * @access private
   *
   * @param string $contents the embedded JSON bytes
   *
   * @return array<array-key, mixed> the decoded document
   */
  private static function decodeJson(string $contents): array
  {
    try {
      $json = json_decode($contents, true, 128, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      throw FacilityModelException::invalid('The GLB JSON is invalid.');
    }
    if (!is_array($json)) {
      throw FacilityModelException::invalid('The GLB asset must declare version 2.0.');
    }

    return $json;
  }

  /**
   * Method validateAsset
   *
   * Requires GLB 2.0 and refuses every unsupported mandatory extension.
   *
   * @access private
   *
   * @param array<array-key, mixed> $json the decoded document
   *
   * @return void
   */
  private static function validateAsset(array $json): void
  {
    if (!is_array($json['asset'] ?? null) || '2.0' !== ($json['asset']['version'] ?? null)
      || (isset($json['asset']['minVersion']) && '2.0' !== $json['asset']['minVersion'])) {
      throw FacilityModelException::invalid('The GLB asset must declare version 2.0.');
    }
    if ([] !== ($json['extensionsRequired'] ?? [])) {
      throw FacilityModelException::invalid('Required glTF extensions are not supported by this importer.');
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
   * @param array<array-key, mixed> $value decoded document or embedded object
   *
   * @return void
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
  // #endregion
}
