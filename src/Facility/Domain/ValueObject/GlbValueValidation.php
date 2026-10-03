<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Facility\Domain\Exception\FacilityModelException;

use function array_is_list;
use function count;
use function is_array;
use function is_finite;
use function is_float;
use function is_int;

/**
 * Class GlbValueValidation
 *
 * Validates the shared bounded collections, indices and numeric values of a GLB document.
 *
 * @category ValueObject
 */
final readonly class GlbValueValidation
{
  // #region Methods
  /**
   * Method items
   *
   * Reads a validated list of embedded glTF objects without coercing malformed values.
   *
   * @access public
   *
   * @param array<array-key, mixed> $json the decoded document or embedded object
   * @param string $key the collection name
   *
   * @return list<array<array-key, mixed>> validated object collection
   */
  public static function items(array $json, string $key): array
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
   * Method index
   *
   * Requires an integer index within its referenced collection.
   *
   * @access public
   *
   * @param mixed $index the untrusted index
   * @param int $count the referenced collection size
   *
   * @return void
   *
   * @phpstan-assert int $index
   */
  public static function index(mixed $index, int $count): void
  {
    if (!is_int($index) || $index < 0 || $index >= $count) {
      throw FacilityModelException::invalid('A GLB index is outside its referenced collection.');
    }
  }

  /**
   * Method range
   *
   * Requires a positive binary range contained in the declared available bytes.
   *
   * @access public
   *
   * @param mixed $offset byte offset
   * @param mixed $length range length in bytes
   * @param mixed $available available byte length
   *
   * @return void
   *
   * @phpstan-assert int $offset
   * @phpstan-assert int $length
   * @phpstan-assert int $available
   */
  public static function range(mixed $offset, mixed $length, mixed $available): void
  {
    if (!is_int($offset) || !is_int($length) || !is_int($available) || $offset < 0 || $length < 1
      || $length > $available || $offset > $available - $length) {
      throw FacilityModelException::invalid('A GLB binary range is out of bounds.');
    }
  }

  /**
   * Method numbers
   *
   * Requires the specified number of finite transform components.
   *
   * @access public
   *
   * @param mixed $numbers the untrusted transform components
   * @param int $length the required number of components
   *
   * @return void
   */
  public static function numbers(mixed $numbers, int $length): void
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
