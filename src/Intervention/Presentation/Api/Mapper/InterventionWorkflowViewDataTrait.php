<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Mapper;

use LogicException;

use function array_keys;
use function array_map;
use function array_values;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;

/**
 * Trait InterventionWorkflowViewDataTrait
 *
 * @category Trait
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait InterventionWorkflowViewDataTrait
{
  // #region Constants
  /**
   * Constant INVALID_LIST_MESSAGE_SUFFIX
   *
   * Shared suffix for errors raised when workflow data is not a list.
   */
  private const string INVALID_LIST_MESSAGE_SUFFIX = ' must be a list of objects.';
  // #endregion

  // #region Methods
  /**
   * Method string
   *
   * Reads a required string field from a decoded workflow view and rejects other values.
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<string, mixed> $data decoded workflow view data
   * @param string $key field name to read
   *
   * @return string the field value
   *
   * @throws LogicException when the field is missing or is not a string
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
   * Method nullableString
   *
   * Reads an optional string field, preserving null and rejecting other values.
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<string, mixed> $data decoded workflow view data
   * @param string $key field name to read
   *
   * @return ?string the field value, or null when absent or explicitly null
   *
   * @throws LogicException when a present field is not a string
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
   * Method integer
   *
   * Reads a required integer field without coercing other values.
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<string, mixed> $data decoded workflow view data
   * @param string $key field name to read
   *
   * @return int the field value
   *
   * @throws LogicException when the field is missing or is not an integer
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
   * Method boolean
   *
   * Reads a required boolean field without coercing other values.
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<string, mixed> $data decoded workflow view data
   * @param string $key field name to read
   *
   * @return bool the field value
   *
   * @throws LogicException when the field is missing or is not a boolean
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
   * Method stringList
   *
   * Reads a list field and verifies that every item is a string.
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<string, mixed> $data decoded workflow view data
   * @param string $key field name to read
   *
   * @return list<string> field values in their original order
   *
   * @throws LogicException when the field is not an array of strings
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
   * Method object
   *
   * Reads an object-shaped field, requiring string keys while preserving nested values.
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<string, mixed> $data decoded workflow view data
   * @param string $key field name to read
   *
   * @return array<string, mixed> field value keyed by strings
   *
   * @throws LogicException when the field is not an object-shaped array
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

  /**
   * Method nullableObject
   *
   * Reads an optional object-shaped field, preserving null when it is absent.
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<string, mixed> $data decoded workflow view data
   * @param string $key field name to read
   *
   * @return ?array<string, mixed> field value, or null when absent or explicitly null
   *
   * @throws LogicException when a present field is not an object-shaped array
   */
  private function nullableObject(array $data, string $key): ?array
  {
    $value = $data[$key] ?? null;
    if (null === $value) {
      return null;
    }

    return $this->object($data, $key);
  }

  /**
   * Method objectList
   *
   * Reads a list of object-shaped arrays and verifies that each item has string keys.
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<string, mixed> $data decoded workflow view data
   * @param string $key field name to read
   *
   * @return list<array<string, mixed>> field objects in their original order
   *
   * @throws LogicException when the field is not a list of object-shaped arrays
   */
  private function objectList(array $data, string $key): array
  {
    $value = $data[$key] ?? null;
    if (!is_array($value)) {
      throw new LogicException($key . self::INVALID_LIST_MESSAGE_SUFFIX);
    }

    return array_values(array_map(function (mixed $item) use ($key): array {
      if (!is_array($item)) {
        throw new LogicException($key . self::INVALID_LIST_MESSAGE_SUFFIX);
      }
      foreach (array_keys($item) as $itemKey) {
        if (!is_string($itemKey)) {
          throw new LogicException($key . self::INVALID_LIST_MESSAGE_SUFFIX);
        }
      }

      /** @var array<string, mixed> $item */
      return $item;
    }, $value));
  }
  // #endregion
}
