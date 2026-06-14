<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Mapper;

use LogicException;

use function array_keys;
use function array_map;
use function array_values;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;

/**
 * Trait MissionWorkflowViewDataTrait.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait MissionWorkflowViewDataTrait
{
  /**
   * Method string.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data
   * @param string $key the key value
   *
   * @return string the string result
   */
  private function string(array $data, string $key): string
  {
    $value = $data[$key] ?? null;
    if (!is_string($value)) {
      throw new LogicException($key . ' must be a string.');
    }

    return $value;
  }

  /**
   * Method nullableString.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data
   * @param string $key the key value
   *
   * @return ?string the nullable string result
   */
  private function nullableString(array $data, string $key): ?string
  {
    $value = $data[$key] ?? null;
    if (null !== $value && !is_string($value)) {
      throw new LogicException($key . ' must be a string or null.');
    }

    return $value;
  }

  /**
   * Method integer.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data
   * @param string $key the key value
   *
   * @return int the integer result
   */
  private function integer(array $data, string $key): int
  {
    $value = $data[$key] ?? null;
    if (!is_int($value)) {
      throw new LogicException($key . ' must be an integer.');
    }

    return $value;
  }

  /**
   * Method boolean.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data
   * @param string $key the key value
   *
   * @return bool the boolean result
   */
  private function boolean(array $data, string $key): bool
  {
    $value = $data[$key] ?? null;
    if (!is_bool($value)) {
      throw new LogicException($key . ' must be a boolean.');
    }

    return $value;
  }

  /**
   * Method stringList.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the data value
   * @param string $key the key value
   *
   * @return list<string>
   */
  private function stringList(array $data, string $key): array
  {
    $value = $data[$key] ?? null;
    if (!is_array($value)) {
      throw new LogicException($key . ' must be a list of strings.');
    }

    return array_values(array_map(static function (mixed $item) use ($key): string {
      if (!is_string($item)) {
        throw new LogicException($key . ' must be a list of strings.');
      }

      return $item;
    }, $value));
  }

  /**
   * Method object.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the data value
   * @param string $key the key value
   *
   * @return array<string, mixed>
   */
  private function object(array $data, string $key): array
  {
    $value = $data[$key] ?? null;
    if (!is_array($value)) {
      throw new LogicException($key . ' must be an object.');
    }
    foreach (array_keys($value) as $objectKey) {
      if (!is_string($objectKey)) {
        throw new LogicException($key . ' must be an object.');
      }
    }

    /** @var array<string, mixed> $value */
    return $value;
  }
}
