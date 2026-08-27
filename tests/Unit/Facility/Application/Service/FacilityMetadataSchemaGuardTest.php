<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\Service;

use DateTimeImmutable;
use Facility\Application\Port\Outbound\FacilityMetadataFieldRepositoryPort;
use Facility\Application\Service\FacilityMetadataSchemaGuard;
use Facility\Domain\Exception\FacilityMetadataValidationException;
use Facility\Domain\Model\MetadataField\FacilityMetadataField;
use Facility\Domain\ValueObject\{
  FacilityMetadataFieldId,
  FacilityMetadataFieldKey,
  FacilityMetadataFieldLabel,
  FacilityMetadataFieldType,
  FacilityOrganizationId,
  FacilityType
};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityMetadataSchemaGuardTest.
 *
 * @category Application Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityMetadataSchemaGuard::class)]
final class FacilityMetadataSchemaGuardTest extends TestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655440100';

  #[Test]
  public function testAssertValidPassesEverythingWhenNoDefinitionsExist(): void
  {
    $guard = $this->guard([]);

    $guard->assertValid(self::ORGANIZATION_ID, ['anything' => 'goes', 'unschemad' => 123], 'site', true);

    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testAssertValidPassesUnknownKeysEvenWithDefinitions(): void
  {
    $guard = $this->guard([$this->definition(FacilityMetadataFieldType::NUMBER)]);

    $guard->assertValid(self::ORGANIZATION_ID, ['unrelated-key' => 'free-form value'], 'site', false);

    $this->addToAssertionCount(1);
  }

  /**
   * @return array<string, array{FacilityMetadataFieldType, mixed, list<string>}>
   */
  public static function validValueProvider(): array
  {
    return [
      'text' => [FacilityMetadataFieldType::TEXT, 'a value', []],
      'number int' => [FacilityMetadataFieldType::NUMBER, 42, []],
      'number float' => [FacilityMetadataFieldType::NUMBER, 4.5, []],
      'boolean' => [FacilityMetadataFieldType::BOOLEAN, true, []],
      'date' => [FacilityMetadataFieldType::DATE, '2026-08-16', []],
      'date-time' => [FacilityMetadataFieldType::DATE, '2026-08-16T10:00:00+00:00', []],
      'select' => [FacilityMetadataFieldType::SELECT, 'ERP', ['ERP', 'IGH']],
    ];
  }

  /**
   * @param list<string> $options
   */
  #[Test]
  #[DataProvider('validValueProvider')]
  public function testAssertValidAcceptsEachTypeWhenItParses(FacilityMetadataFieldType $type, mixed $value, array $options): void
  {
    $guard = $this->guard([$this->definition($type, options: $options)]);

    $guard->assertValid(self::ORGANIZATION_ID, ['the-field' => $value], 'site', false);

    $this->addToAssertionCount(1);
  }

  /**
   * @return array<string, array{FacilityMetadataFieldType, mixed, list<string>}>
   */
  public static function invalidValueProvider(): array
  {
    return [
      'text as int' => [FacilityMetadataFieldType::TEXT, 123, []],
      'number as string' => [FacilityMetadataFieldType::NUMBER, 'not-a-number', []],
      'boolean as string' => [FacilityMetadataFieldType::BOOLEAN, 'true', []],
      'date malformed' => [FacilityMetadataFieldType::DATE, 'not-a-date', []],
      'select not in options' => [FacilityMetadataFieldType::SELECT, 'Unknown', ['ERP', 'IGH']],
    ];
  }

  /**
   * @param list<string> $options
   */
  #[Test]
  #[DataProvider('invalidValueProvider')]
  public function testAssertValidRejectsEachTypeWhenItFailsToParse(FacilityMetadataFieldType $type, mixed $value, array $options): void
  {
    $guard = $this->guard([$this->definition($type, options: $options)]);

    $this->expectException(FacilityMetadataValidationException::class);

    try {
      $guard->assertValid(self::ORGANIZATION_ID, ['the-field' => $value], 'site', false);
    } catch (FacilityMetadataValidationException $exception) {
      self::assertSame(['the-field'], $exception->offendingKeys());

      throw $exception;
    }
  }

  #[Test]
  public function testAssertValidEnforcesRequiredOnlyOnCreate(): void
  {
    $guard = $this->guard([$this->definition(FacilityMetadataFieldType::NUMBER, required: true)]);

    // PATCH omitting the required field must not fail.
    $guard->assertValid(self::ORGANIZATION_ID, [], 'site', false);
    $this->addToAssertionCount(1);

    // CREATE omitting the required field must fail.
    $this->expectException(FacilityMetadataValidationException::class);
    $guard->assertValid(self::ORGANIZATION_ID, [], 'site', true);
  }

  #[Test]
  public function testAssertValidRequiredIsSatisfiedWhenPresent(): void
  {
    $guard = $this->guard([$this->definition(FacilityMetadataFieldType::NUMBER, required: true)]);

    $guard->assertValid(self::ORGANIZATION_ID, ['the-field' => 12], 'site', true);

    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testAssertValidIgnoresDefinitionsScopedToAnotherFacilityType(): void
  {
    $guard = $this->guard([$this->definition(FacilityMetadataFieldType::NUMBER, facilityType: FacilityType::BUILDING, required: true)]);

    // Creating a "site" facility: the "building"-scoped required field does not apply.
    $guard->assertValid(self::ORGANIZATION_ID, [], 'site', true);

    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testAssertValidAppliesDefinitionWithNullFacilityTypeToEveryType(): void
  {
    $guard = $this->guard([$this->definition(FacilityMetadataFieldType::NUMBER, facilityType: null)]);

    $this->expectException(FacilityMetadataValidationException::class);

    $guard->assertValid(self::ORGANIZATION_ID, ['the-field' => 'not-a-number'], 'zone', false);
  }

  /**
   * @param list<FacilityMetadataField> $definitions
   */
  private function guard(array $definitions): FacilityMetadataSchemaGuard
  {
    $repository = $this->createStub(FacilityMetadataFieldRepositoryPort::class);
    $repository->method('findByOrganizationId')->willReturn($definitions);

    return new FacilityMetadataSchemaGuard($repository);
  }

  /**
   * @param list<string> $options
   */
  private function definition(
    FacilityMetadataFieldType $fieldType,
    bool $required = false,
    array $options = [],
    ?FacilityType $facilityType = null,
  ): FacilityMetadataField {
    return FacilityMetadataField::reconstitute(
      id: FacilityMetadataFieldId::fromString('660e8400-e29b-41d4-a716-446655440101'),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      key: new FacilityMetadataFieldKey('the-field'),
      label: new FacilityMetadataFieldLabel('The field'),
      fieldType: $fieldType,
      required: $required,
      createdAt: new DateTimeImmutable(),
      updatedAt: new DateTimeImmutable(),
      options: $options,
      facilityType: $facilityType,
    );
  }
}
