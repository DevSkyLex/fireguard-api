<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\ValueObject;

use Equipment\Domain\ValueObject\{EquipmentIdentity, EquipmentType, EquipmentTypeCode};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function array_fill;

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
    EquipmentIdentity::fromValues(technicalProperties: [['key' => 'charge', 'value' => '6'], ['key' => ' charge ', 'value' => '9']]);
  }

  #[Test]
  public function itRejectsUnknownCriticality(): void
  {
    $this->expectException(InvalidValueException::class);
    EquipmentIdentity::fromValues(criticality: 'urgent');
  }

  #[Test]
  public function itRejectsExcessivePropertiesAndPreservesBuiltInTypes(): void
  {
    self::assertSame(EquipmentType::FIRE_EXTINGUISHER, EquipmentTypeCode::fromString('fire_extinguisher'));
    self::assertSame('water_mist', EquipmentTypeCode::fromString('water_mist')->value);
    $this->expectException(InvalidValueException::class);
    EquipmentIdentity::fromValues(technicalProperties: array_fill(0, 51, ['key' => 'capacity', 'value' => '6']));
  }
}
