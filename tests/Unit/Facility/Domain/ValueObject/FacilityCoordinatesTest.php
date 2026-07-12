<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\ValueObject\FacilityCoordinates;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(FacilityCoordinates::class)]
final class FacilityCoordinatesTest extends TestCase
{
  #[Test]
  public function testConstructorAcceptsValidCoordinates(): void
  {
    $coordinates = new FacilityCoordinates(48.8566, 2.3522);

    self::assertSame(48.8566, $coordinates->latitude());
    self::assertSame(2.3522, $coordinates->longitude());
  }

  #[Test]
  public function testConstructorAcceptsBoundaryValues(): void
  {
    $northEast = new FacilityCoordinates(90.0, 180.0);
    $southWest = new FacilityCoordinates(-90.0, -180.0);

    self::assertSame(90.0, $northEast->latitude());
    self::assertSame(180.0, $northEast->longitude());
    self::assertSame(-90.0, $southWest->latitude());
    self::assertSame(-180.0, $southWest->longitude());
  }

  #[Test]
  public function testConstructorThrowsWhenLatitudeBelowMinimum(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility latitude must be between -90 and 90 degrees.');

    new FacilityCoordinates(-90.1, 2.3522);
  }

  #[Test]
  public function testConstructorThrowsWhenLatitudeAboveMaximum(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility latitude must be between -90 and 90 degrees.');

    new FacilityCoordinates(90.1, 2.3522);
  }

  #[Test]
  public function testConstructorThrowsWhenLongitudeBelowMinimum(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility longitude must be between -180 and 180 degrees.');

    new FacilityCoordinates(48.8566, -180.1);
  }

  #[Test]
  public function testConstructorThrowsWhenLongitudeAboveMaximum(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility longitude must be between -180 and 180 degrees.');

    new FacilityCoordinates(48.8566, 180.1);
  }

  #[Test]
  public function testEqualsReturnsTrueForSameValues(): void
  {
    $a = new FacilityCoordinates(48.8566, 2.3522);
    $b = new FacilityCoordinates(48.8566, 2.3522);

    self::assertTrue($a->equals($b));
  }

  #[Test]
  public function testEqualsReturnsFalseForDifferentLatitude(): void
  {
    $a = new FacilityCoordinates(48.8566, 2.3522);
    $b = new FacilityCoordinates(45.7640, 2.3522);

    self::assertFalse($a->equals($b));
  }

  #[Test]
  public function testEqualsReturnsFalseForDifferentLongitude(): void
  {
    $a = new FacilityCoordinates(48.8566, 2.3522);
    $b = new FacilityCoordinates(48.8566, 4.8357);

    self::assertFalse($a->equals($b));
  }
}
