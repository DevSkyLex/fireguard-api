<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Model\Facility;

use DateTimeImmutable;
use Facility\Domain\Model\Facility\{CanonicalFacility, CanonicalFacilityContent, CanonicalFacilityReference, CanonicalFacilityVersion, Facility, FacilityDetails};
use Facility\Domain\ValueObject\{CanonicalFacilityPatch, FacilityFloorMetrics, FacilityId, FacilityName, FacilityOrganizationId, FacilityRecordStatus, FacilityStatus, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use const INF;
use const NAN;

#[CoversClass(FacilityFloorMetrics::class)]
#[CoversClass(Facility::class)]
#[CoversClass(CanonicalFacility::class)]
final class FacilityFloorMetricsTest extends TestCase
{
  // #region Methods
  /**
   * Creation accepts independent optional metrics and preserves a basement elevation.
   */
  #[Test]
  public function floorCreationPreservesMetrics(): void
  {
    $floor = $this->floor(-3.25, 2.75);

    self::assertSame(-3.25, $floor->elevationMeters());
    self::assertSame(2.75, $floor->heightMeters());
    self::assertNull($floor->levelIndex());
    $floor->changeFloorMetrics(null, 2.75);
    self::assertNull($floor->elevationMeters());
    self::assertSame(2.75, $floor->heightMeters());
  }

  /**
   * Both mutation surfaces clear floor dimensions when changing to a different type.
   */
  #[Test]
  public function changingTypeClearsMetrics(): void
  {
    $floor = $this->floor(-3.25, 2.75);
    $floor->changeType(FacilityType::AREA);
    self::assertNull($floor->elevationMeters());
    self::assertNull($floor->heightMeters());

    $canonical = $this->canonicalFloor();
    $change = $canonical->applyPatch(new CanonicalFacilityPatch(hasType: true, type: 'area'));
    self::assertNull($canonical->elevationMeters());
    self::assertNull($canonical->heightMeters());
    self::assertSame(['type', 'elevationMeters', 'heightMeters'], $change->changedFields);
  }

  /**
   * Absent merge patch fields preserve stored values; explicit null clears only one field.
   */
  #[Test]
  public function canonicalPatchKeepsPresenceSemantics(): void
  {
    $canonical = $this->canonicalFloor();
    $canonical->applyPatch(new CanonicalFacilityPatch(hasName: true, name: 'Basement'));
    self::assertSame(-3.25, $canonical->elevationMeters());
    self::assertSame(2.75, $canonical->heightMeters());

    $change = $canonical->applyPatch(new CanonicalFacilityPatch(hasElevationMeters: true, elevationMeters: null));
    self::assertNull($canonical->elevationMeters());
    self::assertSame(2.75, $canonical->heightMeters());
    self::assertSame(['elevationMeters'], $change->changedFields);
    self::assertSame(3, $canonical->revision());
  }

  /**
   * Every non-floor rejects newly supplied dimensions.
   */
  #[Test]
  public function nonFloorRejectsSuppliedDimensions(): void
  {
    $this->expectException(InvalidValueException::class);
    Facility::create(
      FacilityId::fromString('550e8400-e29b-41d4-a716-446655442000'),
      FacilityOrganizationId::fromString('550e8400-e29b-41d4-a716-446655442001'),
      FacilityType::BUILDING,
      new FacilityName('Building'),
      new FacilityDetails(elevationMeters: 0.0),
    );
  }

  /**
   * Rejecting a type and dimension patch leaves the canonical aggregate untouched.
   */
  #[Test]
  public function invalidCanonicalPatchDoesNotMutateMetrics(): void
  {
    $canonical = $this->canonicalFloor();

    try {
      $canonical->applyPatch(new CanonicalFacilityPatch(
        hasType: true,
        type: 'building',
        hasHeightMeters: true,
        heightMeters: 4.0,
      ));
      self::fail('A building cannot carry floor dimensions.');
    } catch (InvalidValueException) {
      self::assertSame(FacilityType::FLOOR, $canonical->type());
      self::assertSame(-3.25, $canonical->elevationMeters());
      self::assertSame(2.75, $canonical->heightMeters());
      self::assertSame(1, $canonical->revision());
    }
  }

  /**
   * Invalid metrics are rejected by the shared domain rule.
   */
  #[Test]
  #[DataProvider('invalidMetrics')]
  public function invalidMetricsAreRejected(?float $elevation, ?float $height): void
  {
    $this->expectException(InvalidValueException::class);
    $this->floor($elevation, $height);
  }

  /**
   * @return iterable<string, array{?float, ?float}>
   */
  public static function invalidMetrics(): iterable
  {
    yield 'low elevation' => [-10000.1, null];
    yield 'high elevation' => [10000.1, null];
    yield 'nan elevation' => [NAN, null];
    yield 'infinite elevation' => [INF, null];
    yield 'zero height' => [null, 0.0];
    yield 'negative height' => [null, -1.0];
    yield 'high height' => [null, 1000.1];
    yield 'nan height' => [null, NAN];
    yield 'infinite height' => [null, INF];
  }

  /**
   * Optional metrics can use the supported inclusive endpoints.
   */
  #[Test]
  public function boundariesAreAccepted(): void
  {
    self::assertSame(-10000.0, $this->floor(-10000.0, 1000.0)->elevationMeters());
    self::assertSame(1000.0, $this->floor(10000.0, 1000.0)->heightMeters());
    self::assertNull($this->floor(null, null)->heightMeters());
  }

  /**
   * Builds a floor with independent physical dimensions.
   */
  private function floor(?float $elevation, ?float $height): Facility
  {
    return Facility::create(
      FacilityId::fromString('550e8400-e29b-41d4-a716-446655442000'),
      FacilityOrganizationId::fromString('550e8400-e29b-41d4-a716-446655442001'),
      FacilityType::FLOOR,
      new FacilityName('Floor'),
      new FacilityDetails(elevationMeters: $elevation, heightMeters: $height),
    );
  }

  /**
   * Builds a canonical basement at its initial revision.
   */
  private function canonicalFloor(): CanonicalFacility
  {
    return CanonicalFacility::reconstitute(
      new CanonicalFacilityReference(
        FacilityId::fromString('550e8400-e29b-41d4-a716-446655442000'),
        FacilityOrganizationId::fromString('550e8400-e29b-41d4-a716-446655442001'),
        FacilityRecordStatus::PUBLISHED,
        null,
        null,
      ),
      new CanonicalFacilityContent(FacilityType::FLOOR, 'Floor', null, null, null, null, []),
      new CanonicalFacilityVersion(FacilityStatus::ACTIVE, 1, new DateTimeImmutable(), null, -3.25, 2.75),
    );
  }
  // #endregion
}
