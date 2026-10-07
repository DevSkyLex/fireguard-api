<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\EquipmentTypeCatalog;

use Equipment\Application\Port\Outbound\EquipmentTypeCatalogPort;
use Equipment\Application\UseCase\Command\EquipmentTypeCatalog\CreateEquipmentType\{CreateEquipmentTypeCommand, CreateEquipmentTypeHandler};
use Equipment\Application\UseCase\Command\EquipmentTypeCatalog\PatchEquipmentType\{PatchEquipmentTypeCommand, PatchEquipmentTypeHandler};
use Equipment\Application\UseCase\Query\EquipmentTypeCatalog\ListEquipmentTypes\{ListEquipmentTypesHandler, ListEquipmentTypesQuery};
use Equipment\Domain\Exception\EquipmentTypeCatalogException;
use Equipment\Domain\Model\EquipmentTypeCatalog\EquipmentTypeDefinition;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test EquipmentTypeCatalogHandlersTest.
 *
 * @category Tests
 */
#[CoversClass(CreateEquipmentTypeHandler::class)]
#[CoversClass(PatchEquipmentTypeHandler::class)]
#[CoversClass(ListEquipmentTypesHandler::class)]
final class EquipmentTypeCatalogHandlersTest extends TestCase
{
  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655491001';

  #[Test]
  public function testCreationWritesAnActiveDescriptorAtRevisionOne(): void
  {
    $catalog = $this->createMock(EquipmentTypeCatalogPort::class);
    $catalog->expects(self::once())->method('save')->with(
      self::ORGANIZATION_ID,
      self::callback(static fn (EquipmentTypeDefinition $type): bool => 'water_mist' === $type->value && 'Water mist' === $type->label && 'fire' === $type->family && !$type->archived && 1 === $type->revision),
      null,
    );
    $result = new CreateEquipmentTypeHandler($catalog)(new CreateEquipmentTypeCommand(self::ORGANIZATION_ID, 'water_mist', ' Water mist ', 'fire'));
    self::assertSame('water_mist', $result->type->value);
    self::assertSame('Water mist', $result->type->label);
    self::assertSame(1, $result->type->revision);
  }

  #[Test]
  public function testListPreservesArchiveMetadataAndOrganizationScope(): void
  {
    $catalog = $this->createMock(EquipmentTypeCatalogPort::class);
    $catalog->expects(self::once())->method('list')->with(self::ORGANIZATION_ID)->willReturn([
      new EquipmentTypeDefinition('old_unit', 'Old unit', 'other', true, 3),
    ]);
    $result = new ListEquipmentTypesHandler($catalog)(new ListEquipmentTypesQuery(self::ORGANIZATION_ID));
    self::assertCount(1, $result->types);
    self::assertSame('old_unit', $result->types[0]->value);
    self::assertSame('Old unit', $result->types[0]->label);
    self::assertSame('other', $result->types[0]->family);
    self::assertTrue($result->types[0]->archived);
    self::assertSame(3, $result->types[0]->revision);
  }

  #[Test]
  public function testPatchArchivesWithOptimisticRevision(): void
  {
    $catalog = $this->createMock(EquipmentTypeCatalogPort::class);
    $catalog->expects(self::once())->method('find')->with(self::ORGANIZATION_ID, 'fire_extinguisher')->willReturn(new EquipmentTypeDefinition('fire_extinguisher', 'Fire Extinguisher', 'fire'));
    $catalog->expects(self::once())->method('save')->with(
      self::ORGANIZATION_ID,
      self::callback(static fn (EquipmentTypeDefinition $type): bool => $type->archived && 2 === $type->revision),
      1,
    );
    $result = new PatchEquipmentTypeHandler($catalog)(new PatchEquipmentTypeCommand(self::ORGANIZATION_ID, 'fire_extinguisher', 1, archived: true));
    self::assertTrue($result->type->archived);
    self::assertSame(2, $result->type->revision);
  }

  #[Test]
  public function testStalePatchNeverPersists(): void
  {
    $catalog = $this->createMock(EquipmentTypeCatalogPort::class);
    $catalog->method('find')->willReturn(new EquipmentTypeDefinition('water_mist', 'Water mist', 'fire', false, 2));
    $catalog->expects(self::never())->method('save');
    $this->expectException(EquipmentTypeCatalogException::class);
    new PatchEquipmentTypeHandler($catalog)(new PatchEquipmentTypeCommand(self::ORGANIZATION_ID, 'water_mist', 1, archived: true));
  }

  #[Test]
  public function testUnknownPatchNeverPersists(): void
  {
    $catalog = $this->createMock(EquipmentTypeCatalogPort::class);
    $catalog->method('find')->willReturn(null);
    $catalog->expects(self::never())->method('save');
    $this->expectException(EquipmentTypeCatalogException::class);
    new PatchEquipmentTypeHandler($catalog)(new PatchEquipmentTypeCommand(self::ORGANIZATION_ID, 'unknown_type', 1));
  }
}
