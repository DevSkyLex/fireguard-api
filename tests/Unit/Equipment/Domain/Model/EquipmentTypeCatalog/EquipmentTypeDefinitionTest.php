<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Model\EquipmentTypeCatalog;

use Equipment\Domain\Exception\EquipmentTypeCatalogException;
use Equipment\Domain\Model\EquipmentTypeCatalog\EquipmentTypeDefinition;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test EquipmentTypeDefinitionTest.
 *
 * @category Tests
 */
#[CoversClass(EquipmentTypeDefinition::class)]
final class EquipmentTypeDefinitionTest extends TestCase
{
  #[Test]
  public function testArchivedTypeRemainsValidOnlyForItsExistingAssignment(): void
  {
    $type = new EquipmentTypeDefinition('fire_extinguisher', 'Extinguisher', 'fire', true);
    $type->assertAvailable('fire_extinguisher');
    $this->expectException(EquipmentTypeCatalogException::class);
    $type->assertAvailable(null);
  }

  #[Test]
  public function testRevisionChangesOnlyForRealEditsAndCodeRemainsStable(): void
  {
    $type = EquipmentTypeDefinition::create('water_mist', ' Water mist ', 'fire');
    self::assertSame('Water mist', $type->label);
    self::assertSame(1, $type->revise(null, null, null)->revision);
    $archived = $type->revise('Mist unit', 'safety', true);
    self::assertSame('water_mist', $archived->value);
    self::assertSame(2, $archived->revision);
    self::assertSame('Mist unit', $archived->label);
    self::assertSame('safety', $archived->family);
    self::assertTrue($archived->archived);
  }

  #[Test]
  #[DataProvider('invalidDefinitions')]
  public function testInvalidDefinitionsAreRejected(string $code, string $label, string $family): void
  {
    $this->expectException(EquipmentTypeCatalogException::class);
    EquipmentTypeDefinition::create($code, $label, $family);
  }

  /**
   * Method invalidDefinitions.
   *
   * @return iterable<string, array{string, string, string}> invalid catalog inputs
   */
  public static function invalidDefinitions(): iterable
  {
    yield 'uppercase' => ['Mist', 'Mist unit', 'fire'];
    yield 'long code' => ['abcdefghijklmnopqrstuvwxyzabcdefg', 'Mist unit', 'fire'];
    yield 'empty label' => ['mist', '   ', 'fire'];
    yield 'unknown family' => ['mist', 'Mist unit', 'industrial'];
  }
}
