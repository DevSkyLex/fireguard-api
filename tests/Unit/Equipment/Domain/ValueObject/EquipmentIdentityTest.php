<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\ValueObject;

use Equipment\Domain\ValueObject\{EquipmentIdentity, EquipmentType, EquipmentTypeCode};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function array_fill;
use function str_repeat;

final class EquipmentIdentityTest extends TestCase
{
  #[Test]
  public function itNormalizesIdentityAndKeepsExplicitProperties(): void
  {
    $identity = EquipmentIdentity::fromValues(' CO2 Stair B ', ' EXT-001 ', 'critical', [['key' => 'charge', 'value' => '6', 'unit' => ' kg ']]);
    self::assertSame('CO2 Stair B', $identity->name);
    self::assertSame('EXT-001', $identity->assetCode);
    self::assertSame([['key' => 'charge', 'value' => '6', 'unit' => 'kg']], $identity->technicalProperties);
    self::assertNull(EquipmentIdentity::fromValues(' ', ' ')->assetCode);
  }

  #[Test]
  public function itRejectsRepeatedKeysAfterNormalization(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Technical property keys must be nonempty and unique.');
    EquipmentIdentity::fromValues(technicalProperties: [['key' => 'charge', 'value' => '6'], ['key' => ' charge ', 'value' => '9']]);
  }

  #[Test]
  public function itRejectsUnknownCriticality(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Equipment criticality is invalid.');
    EquipmentIdentity::fromValues(criticality: 'urgent');
  }

  #[Test]
  public function itRejectsExcessivePropertiesAndPreservesBuiltInTypes(): void
  {
    self::assertSame(EquipmentType::FIRE_EXTINGUISHER, EquipmentTypeCode::fromString('fire_extinguisher'));
    self::assertSame('water_mist', EquipmentTypeCode::fromString('water_mist')->value);
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Technical properties must be a list of at most 50 entries.');
    EquipmentIdentity::fromValues(technicalProperties: array_fill(0, 51, ['key' => 'capacity', 'value' => '6']));
  }

  #[Test]
  public function itPreservesPropertyValuesAndUsesCharacterLimitsAfterTrimming(): void
  {
    $key = str_repeat('é', 64);
    $value = ' ' . str_repeat('é', 253) . ' ';
    $unit = str_repeat('é', 32);

    $identity = EquipmentIdentity::fromValues(technicalProperties: [
      ['key' => ' ' . $key . ' ', 'value' => $value, 'unit' => ' ' . $unit . ' '],
      ['key' => 'unitless', 'value' => '', 'unit' => ' '],
      ['key' => 'omitted', 'value' => '  unchanged  '],
    ]);

    self::assertSame([
      ['key' => $key, 'value' => $value, 'unit' => $unit],
      ['key' => 'unitless', 'value' => '', 'unit' => null],
      ['key' => 'omitted', 'value' => '  unchanged  ', 'unit' => null],
    ], $identity->technicalProperties);
  }

  /**
   * @param array<mixed> $properties
   */
  #[Test]
  #[DataProvider('invalidProperties')]
  public function itPreservesValidationErrorsAndTheirPrecedence(?string $criticality, array $properties, string $message): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage($message);

    EquipmentIdentity::fromValues(name: str_repeat('a', 256), criticality: $criticality, technicalProperties: $properties);
  }

  /**
   * @return iterable<string, array{?string, array<mixed>, string}>
   */
  public static function invalidProperties(): iterable
  {
    yield 'criticality before list shape' => ['urgent', ['named' => null], 'Equipment criticality is invalid.'];
    yield 'list shape before entry shape' => [null, [1 => null], 'Technical properties must be a list of at most 50 entries.'];
    yield 'list size before entry shape' => [null, array_fill(0, 51, null), 'Technical properties must be a list of at most 50 entries.'];
    yield 'entry must be an array' => [null, ['property'], 'Technical properties require a string key, value and optional unit.'];
    yield 'missing key' => [null, [['value' => '6']], 'Technical properties require a string key, value and optional unit.'];
    yield 'non-string value' => [null, [['key' => 'charge', 'value' => 6]], 'Technical properties require a string key, value and optional unit.'];
    yield 'unit type before empty key' => [null, [['key' => ' ', 'value' => '6', 'unit' => 6]], 'Technical properties require a string key, value and optional unit.'];
    yield 'empty key before value length' => [null, [['key' => ' ', 'value' => str_repeat('a', 256)]], 'Technical property keys must be nonempty and unique.'];
    yield 'duplicate key before value length' => [null, [['key' => 'charge', 'value' => '6'], ['key' => ' charge ', 'value' => str_repeat('a', 256)]], 'Technical property keys must be nonempty and unique.'];
    yield 'key length before value length' => [null, [['key' => str_repeat('a', 65), 'value' => str_repeat('a', 256)]], 'Equipment identity text exceeds its maximum length.'];
    yield 'value length before unit length' => [null, [['key' => 'charge', 'value' => str_repeat('a', 256), 'unit' => str_repeat('a', 33)]], 'Technical property values must be at most 255 characters.'];
    yield 'unit length before name normalization' => [null, [['key' => 'charge', 'value' => '6', 'unit' => str_repeat('a', 33)]], 'Equipment identity text exceeds its maximum length.'];
  }
}
