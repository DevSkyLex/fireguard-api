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
 * Parses workflow mutation fields without changing their validation rules.
 */
final readonly class InterventionWorkflowPayload
{
  /**
   * Method requiredString.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $payload
   * @param string $key the key value
   *
   * @return string the required string result
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
   * Method nullableString.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $payload
   * @param string $key the key value
   *
   * @return ?string the nullable string result
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
   * Method stringList.
   *
   * @since 1.0.0
   *
   * @param mixed $value the value value
   *
   * @return list<string>
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
   * Method date.
   *
   * Executes the date operation.
   *
   * @since 1.0.0
   *
   * @param mixed $value the value value
   *
   * @return ?DateTimeImmutable the date result
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
   * Method patch.
   *
   * @since 1.0.0
   *
   * @param mixed $value the value value
   *
   * @return array<string, mixed>
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
}
