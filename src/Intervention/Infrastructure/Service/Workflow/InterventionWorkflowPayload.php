<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Service\Workflow;

use DateTimeImmutable;
use InvalidArgumentException;

use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function is_array;
use function is_string;

/**
 * Class InterventionWorkflowPayload
 *
 * Validates workflow mutation fields using the established input rules.
 *
 * @category Service
 */
final readonly class InterventionWorkflowPayload
{
  // #region Methods
  /**
   * Method requiredString
   *
   * Returns a required, non-empty string field without coercing other values.
   *
   * @access public
   * @since 1.0.0
   *
   * @param array<string, mixed> $payload workflow mutation fields
   * @param string $key name of the required field
   *
   * @return string the non-empty field value
   *
   * @throws InvalidArgumentException when the field is missing, empty or not a string
   */
  public static function requiredString(array $payload, string $key): string
  {
    $value = $payload[$key] ?? null;
    if (!is_string($value) || '' === $value) {
      throw new InvalidArgumentException($key . ' must be a non-empty string.');
    }

    return $value;
  }

  /**
   * Method nullableString
   *
   * Returns an optional string field, preserving null and rejecting other values.
   *
   * @access public
   * @since 1.0.0
   *
   * @param array<string, mixed> $payload workflow mutation fields
   * @param string $key name of the optional field
   *
   * @return ?string the field value, or null when absent or explicitly null
   *
   * @throws InvalidArgumentException when a present field is not a string
   */
  public static function nullableString(array $payload, string $key): ?string
  {
    $value = $payload[$key] ?? null;
    if (null === $value) {
      return null;
    }
    if (!is_string($value)) {
      throw new InvalidArgumentException($key . ' must be a string or null.');
    }

    return $value;
  }

  /**
   * Method stringList
   *
   * Validates an array of strings, removes duplicate values and reindexes the result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $value candidate list value
   *
   * @return list<string> unique string values in first-seen order
   *
   * @throws InvalidArgumentException when the value is not an array of strings
   */
  public static function stringList(mixed $value): array
  {
    if (!is_array($value)) {
      throw new InvalidArgumentException('Expected a list of strings.');
    }

    return array_values(array_unique(array_map(
      static function (mixed $item): string {
        if (!is_string($item)) {
          throw new InvalidArgumentException('Expected a list of strings.');
        }

        return $item;
      },
      $value,
    )));
  }

  /**
   * Method date
   *
   * Converts an optional date-time string to an immutable date value.
   * Malformed strings propagate DateTimeImmutable's parsing exception.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $value candidate date-time value
   *
   * @return ?DateTimeImmutable parsed date, or null when the value is null
   *
   * @throws InvalidArgumentException when a non-null value is not a string
   */
  public static function date(mixed $value): ?DateTimeImmutable
  {
    if (null === $value) {
      return null;
    }
    if (!is_string($value)) {
      throw new InvalidArgumentException('Expected a date-time string or null.');
    }

    return new DateTimeImmutable($value);
  }

  /**
   * Method patch
   *
   * Validates an object-shaped patch while preserving its nested values.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $value candidate patch value
   *
   * @return array<string, mixed> patch fields keyed by strings
   *
   * @throws InvalidArgumentException when the value is not an object-shaped array
   */
  public static function patch(mixed $value): array
  {
    if (!is_array($value)) {
      throw new InvalidArgumentException('Patch must be an object.');
    }
    foreach (array_keys($value) as $key) {
      if (!is_string($key)) {
        throw new InvalidArgumentException('Patch must be an object.');
      }
    }

    /** @var array<string, mixed> $value */
    return $value;
  }
  // #endregion
}
