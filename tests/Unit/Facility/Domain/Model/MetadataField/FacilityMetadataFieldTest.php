<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Model\MetadataField;

use Facility\Domain\Model\MetadataField\FacilityMetadataField;
use Facility\Domain\ValueObject\{
  FacilityMetadataFieldId,
  FacilityMetadataFieldKey,
  FacilityMetadataFieldLabel,
  FacilityMetadataFieldType,
  FacilityOrganizationId,
  FacilityType
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test FacilityMetadataFieldTest.
 *
 * @category Domain Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityMetadataField::class)]
final class FacilityMetadataFieldTest extends TestCase
{
  private const string ID = '660e8400-e29b-41d4-a716-446655440000';

  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testCreateBuildsAnActiveDefinition(): void
  {
    $field = $this->create(fieldType: FacilityMetadataFieldType::NUMBER, unit: 'm²', required: true);

    self::assertSame(self::ID, (string) $field->id());
    self::assertSame(self::ORGANIZATION_ID, (string) $field->organizationId());
    self::assertSame('surface-m2', (string) $field->key());
    self::assertSame('Surface (m²)', (string) $field->label());
    self::assertSame(FacilityMetadataFieldType::NUMBER, $field->fieldType());
    self::assertTrue($field->required());
    self::assertSame([], $field->options());
    self::assertNull($field->facilityType());
    self::assertSame('m²', $field->unit());
  }

  #[Test]
  public function testKeyRejectsUppercaseAndSpaces(): void
  {
    $this->expectException(InvalidValueException::class);

    new FacilityMetadataFieldKey('Not A Valid Key');
  }

  #[Test]
  public function testKeyAcceptsKebabAndSnakeCase(): void
  {
    self::assertSame('surface-m2', (string) new FacilityMetadataFieldKey('surface-m2'));
    self::assertSame('occupancy_load', (string) new FacilityMetadataFieldKey('occupancy_load'));
  }

  #[Test]
  public function testKeyRejectsTooShortOrTooLong(): void
  {
    $this->expectException(InvalidValueException::class);

    new FacilityMetadataFieldKey('a');
  }

  #[Test]
  public function testSelectFieldRequiresAtLeastTwoOptions(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('requires at least two options');

    $this->create(fieldType: FacilityMetadataFieldType::SELECT, options: ['ERP']);
  }

  #[Test]
  public function testSelectFieldRejectsDuplicateOptions(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('must be unique');

    $this->create(fieldType: FacilityMetadataFieldType::SELECT, options: ['ERP', 'ERP']);
  }

  #[Test]
  public function testSelectFieldAcceptsValidOptions(): void
  {
    $field = $this->create(fieldType: FacilityMetadataFieldType::SELECT, options: ['ERP', 'IGH', 'Habitation']);

    self::assertSame(['ERP', 'IGH', 'Habitation'], $field->options());
  }

  #[Test]
  public function testNonSelectFieldSilentlyDropsOptions(): void
  {
    $field = $this->create(fieldType: FacilityMetadataFieldType::TEXT, options: ['ignored']);

    self::assertSame([], $field->options());
  }

  #[Test]
  public function testUnitRejectsMoreThanSixteenCharacters(): void
  {
    $this->expectException(InvalidValueException::class);

    $this->create(unit: 'a-unit-too-long-to-fit');
  }

  #[Test]
  public function testUnitBlankIsNormalizedToNull(): void
  {
    $field = $this->create(unit: '   ');

    self::assertNull($field->unit());
  }

  #[Test]
  public function testChangeTypeToSelectRequiresOptions(): void
  {
    $field = $this->create(fieldType: FacilityMetadataFieldType::TEXT);

    $this->expectException(InvalidValueException::class);

    $field->changeType(FacilityMetadataFieldType::SELECT, []);
  }

  #[Test]
  public function testChangeTypeAwayFromSelectDropsOptions(): void
  {
    $field = $this->create(fieldType: FacilityMetadataFieldType::SELECT, options: ['A', 'B']);

    $field->changeType(FacilityMetadataFieldType::TEXT);

    self::assertSame([], $field->options());
    self::assertSame(FacilityMetadataFieldType::TEXT, $field->fieldType());
  }

  #[Test]
  public function testChangeFacilityTypeScopesTheDefinition(): void
  {
    $field = $this->create();

    $field->changeFacilityType(FacilityType::BUILDING);

    self::assertSame(FacilityType::BUILDING, $field->facilityType());
  }

  #[Test]
  public function testRenameChangesLabel(): void
  {
    $field = $this->create();
    $before = $field->updatedAt();

    $field->rename(new FacilityMetadataFieldLabel('New label'));

    self::assertSame('New label', (string) $field->label());
    self::assertGreaterThanOrEqual($before, $field->updatedAt());
  }

  /**
   * @param list<string> $options
   */
  private function create(
    FacilityMetadataFieldType $fieldType = FacilityMetadataFieldType::TEXT,
    bool $required = false,
    array $options = [],
    ?string $unit = null,
  ): FacilityMetadataField {
    return FacilityMetadataField::create(
      id: FacilityMetadataFieldId::fromString(self::ID),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      key: new FacilityMetadataFieldKey('surface-m2'),
      label: new FacilityMetadataFieldLabel('Surface (m²)'),
      fieldType: $fieldType,
      required: $required,
      options: $options,
      unit: $unit,
    );
  }
}
