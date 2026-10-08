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
 * Class EquipmentIdentity
 *
 * Describes an asset independently from its operational condition.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentIdentity
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Stores the normalized identity after its ordered validation.
   *
   * @access private
   * @since 1.0.0
   *
   * @param ?string $name optional declared equipment label
   * @param ?string $assetCode optional organization-owned asset reference
   * @param ?string $criticality optional declared criticality
   * @param list<array{key: string, value: string, unit: ?string}> $technicalProperties descriptive properties
   *
   * @return void
   */
  private function __construct(
    public ?string $name,
    public ?string $assetCode,
    public ?string $criticality,
    public array $technicalProperties,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromValues
   *
   * Validates criticality and technical properties before normalizing identity text.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ?string $name optional declared equipment label
   * @param ?string $assetCode optional organization-owned asset reference
   * @param ?string $criticality optional declared criticality
   * @param array<mixed> $technicalProperties declarative properties
   *
   * @return self the normalized identity
   */
  public static function fromValues(?string $name = null, ?string $assetCode = null, ?string $criticality = null, array $technicalProperties = []): self
  {
    if (null !== $criticality && !in_array($criticality, ['low', 'medium', 'high', 'critical'], true)) {
      throw InvalidValueException::because('Equipment criticality is invalid.');
    }
    $properties = self::normalizeTechnicalProperties($technicalProperties);

    return new self(self::text($name, 255), self::text($assetCode, 100), $criticality, $properties);
  }

  /**
   * Method normalizeTechnicalProperties
   *
   * Validates the bounded list and preserves entry order and normalized key uniqueness.
   *
   * @access private
   *
   * @param array<mixed> $technicalProperties declarative properties
   *
   * @return list<array{key: string, value: string, unit: ?string}> the normalized properties
   */
  private static function normalizeTechnicalProperties(array $technicalProperties): array
  {
    if (!array_is_list($technicalProperties) || count($technicalProperties) > 50) {
      throw InvalidValueException::because('Technical properties must be a list of at most 50 entries.');
    }
    $properties = [];
    $keys = [];
    foreach ($technicalProperties as $property) {
      $normalized = self::normalizeTechnicalProperty($property, $keys);
      $keys[] = $normalized['key'];
      $properties[] = $normalized;
    }

    return $properties;
  }

  /**
   * Method normalizeTechnicalProperty
   *
   * Checks one entry's shape, key and value before normalizing its optional unit.
   *
   * @access private
   *
   * @param mixed $property an unvalidated property entry
   * @param list<string> $keys previously accepted normalized keys
   *
   * @return array{key: string, value: string, unit: ?string} the normalized entry
   */
  private static function normalizeTechnicalProperty(mixed $property, array $keys): array
  {
    self::assertTechnicalPropertyShape($property);
    $key = self::text($property['key'], 64);
    if (null === $key || in_array($key, $keys, true)) {
      throw InvalidValueException::because('Technical property keys must be nonempty and unique.');
    }
    $value = $property['value'];
    if (mb_strlen($value) > 255) {
      throw InvalidValueException::because('Technical property values must be at most 255 characters.');
    }

    return ['key' => $key, 'value' => $value, 'unit' => self::normalizeTechnicalPropertyUnit($property['unit'] ?? null)];
  }

  /**
   * Method assertTechnicalPropertyShape
   *
   * Rejects malformed fields before any key or value content validation.
   *
   * @access private
   *
   * @param mixed $property an unvalidated property entry
   *
   * @return void
   *
   * @phpstan-assert array{key: string, value: string, unit?: ?string} $property
   */
  private static function assertTechnicalPropertyShape(mixed $property): void
  {
    if (!is_array($property) || !is_string($property['key'] ?? null) || !is_string($property['value'] ?? null)
      || (null !== ($property['unit'] ?? null) && !is_string($property['unit']))) {
      throw InvalidValueException::because('Technical properties require a string key, value and optional unit.');
    }
  }

  /**
   * Method normalizeTechnicalPropertyUnit
   *
   * Normalizes optional unit text using the unit-specific type and length limits.
   *
   * @access private
   *
   * @param mixed $unit the optional unit representation
   *
   * @return ?string the normalized unit
   */
  private static function normalizeTechnicalPropertyUnit(mixed $unit): ?string
  {
    if (null !== $unit && !is_string($unit)) {
      throw InvalidValueException::because('Technical property unit must be a string or null.');
    }

    return self::text($unit, 32);
  }

  /**
   * Method text
   *
   * Trims optional identity text and enforces its maximum character length.
   *
   * @access private
   * @since 1.0.0
   *
   * @param ?string $value the optional text representation
   * @param int $maximum the maximum number of characters after trimming
   *
   * @return ?string the normalized text
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
  // #endregion
}
