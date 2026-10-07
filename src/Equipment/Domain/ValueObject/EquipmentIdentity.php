<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function array_is_list;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function mb_strlen;
use function trim;

/**
 * Declarative asset identity, independent from operational condition.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentIdentity
{
  /**
   * @since 1.0.0
   *
   * @param list<array{key: string, value: string, unit: ?string}> $technicalProperties descriptive properties
   */
  private function __construct(
    public ?string $name,
    public ?string $assetCode,
    public ?string $criticality,
    public array $technicalProperties,
  ) {
  }

  /**
   * @since 1.0.0
   *
   * @param array<mixed> $technicalProperties declarative properties
   */
  public static function fromValues(?string $name = null, ?string $assetCode = null, ?string $criticality = null, array $technicalProperties = []): self
  {
    if (null !== $criticality && !in_array($criticality, ['low', 'medium', 'high', 'critical'], true)) {
      throw InvalidValueException::because('Equipment criticality is invalid.');
    }
    if (!array_is_list($technicalProperties) || count($technicalProperties) > 50) {
      throw InvalidValueException::because('Technical properties must be a list of at most 50 entries.');
    }
    $properties = [];
    $keys = [];
    foreach ($technicalProperties as $property) {
      if (!is_array($property) || !is_string($property['key'] ?? null) || !is_string($property['value'] ?? null)
        || (null !== ($property['unit'] ?? null) && !is_string($property['unit']))) {
        throw InvalidValueException::because('Technical properties require a string key, value and optional unit.');
      }
      $key = self::text($property['key'], 64);
      if (null === $key || in_array($key, $keys, true)) {
        throw InvalidValueException::because('Technical property keys must be nonempty and unique.');
      }
      $value = $property['value'];
      if (mb_strlen($value) > 255) {
        throw InvalidValueException::because('Technical property values must be at most 255 characters.');
      }
      $keys[] = $key;
      $unit = $property['unit'] ?? null;
      if (null !== $unit && !is_string($unit)) {
        throw InvalidValueException::because('Technical property unit must be a string or null.');
      }
      $properties[] = ['key' => $key, 'value' => $value, 'unit' => self::text($unit, 32)];
    }

    return new self(self::text($name, 255), self::text($assetCode, 100), $criticality, $properties);
  }

  /**
   * @since 1.0.0
   */
  private static function text(?string $value, int $maximum): ?string
  {
    if (null === $value || '' === trim($value)) {
      return null;
    }
    if (mb_strlen(trim($value)) > $maximum) {
      throw InvalidValueException::because('Equipment identity text exceeds its maximum length.');
    }

    return trim($value);
  }
}
