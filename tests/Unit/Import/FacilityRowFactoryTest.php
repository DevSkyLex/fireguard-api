<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use Facility\Application\Contract\Provisioning\ProvisionFacilityRequest;
use Import\Application\Service\FacilityRowFactory;
use Import\Domain\Exception\ImportRowValidationException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityRowFactoryTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityRowFactory::class)]
final class FacilityRowFactoryTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f67a01';

  #[Test]
  public function itMapsAFullyPopulatedRow(): void
  {
    $request = new FacilityRowFactory()->map(self::ORGANIZATION_ID, [
      'type' => 'site',
      'name' => 'Main site',
      'code' => 'SITE-1',
      'address' => '1 Rue de la Paix',
      'latitude' => '48.8566',
      'longitude' => '2.3522',
      'parentCode' => 'HQ',
    ]);

    self::assertInstanceOf(ProvisionFacilityRequest::class, $request);
    self::assertSame(self::ORGANIZATION_ID, $request->organizationId);
    self::assertSame('site', $request->type);
    self::assertSame('Main site', $request->name);
    self::assertSame('SITE-1', $request->code);
    self::assertSame('1 Rue de la Paix', $request->address);
    self::assertSame(48.8566, $request->latitude);
    self::assertSame(2.3522, $request->longitude);
    self::assertSame('HQ', $request->parentCode);
  }

  #[Test]
  public function itTrimsValuesAndTreatsBlankOptionalColumnsAsNull(): void
  {
    $request = new FacilityRowFactory()->map(self::ORGANIZATION_ID, [
      'type' => '  site  ',
      'name' => '  Main site  ',
      'code' => '',
      'parentCode' => '   ',
    ]);

    self::assertSame('site', $request->type);
    self::assertSame('Main site', $request->name);
    self::assertNull($request->code);
    self::assertNull($request->address);
    self::assertNull($request->latitude);
    self::assertNull($request->longitude);
    self::assertNull($request->parentCode);
  }

  #[Test]
  public function itRejectsAMissingRequiredNameColumn(): void
  {
    try {
      new FacilityRowFactory()->map(self::ORGANIZATION_ID, ['type' => 'site']);
      self::fail('Expected an ImportRowValidationException.');
    } catch (ImportRowValidationException $exception) {
      self::assertSame('missing_required', $exception->errorCode);
      self::assertSame('name', $exception->column);
    }
  }

  #[Test]
  public function itRejectsLatitudeWithoutLongitude(): void
  {
    try {
      new FacilityRowFactory()->map(self::ORGANIZATION_ID, [
        'type' => 'site',
        'name' => 'Main site',
        'latitude' => '48.8566',
      ]);
      self::fail('Expected an ImportRowValidationException.');
    } catch (ImportRowValidationException $exception) {
      self::assertSame('invalid', $exception->errorCode);
      self::assertSame('longitude', $exception->column);
    }
  }

  #[Test]
  public function itRejectsANonNumericLatitude(): void
  {
    try {
      new FacilityRowFactory()->map(self::ORGANIZATION_ID, [
        'type' => 'site',
        'name' => 'Main site',
        'latitude' => 'north',
        'longitude' => '2.3522',
      ]);
      self::fail('Expected an ImportRowValidationException.');
    } catch (ImportRowValidationException $exception) {
      self::assertSame('invalid', $exception->errorCode);
      self::assertSame('latitude', $exception->column);
    }
  }

  #[Test]
  public function itRejectsALatitudeAboveNinetyDegrees(): void
  {
    try {
      new FacilityRowFactory()->map(self::ORGANIZATION_ID, [
        'type' => 'site',
        'name' => 'Main site',
        'latitude' => '90.1',
        'longitude' => '2.3522',
      ]);
      self::fail('Expected an ImportRowValidationException.');
    } catch (ImportRowValidationException $exception) {
      self::assertSame('invalid', $exception->errorCode);
      self::assertSame('latitude', $exception->column);
    }
  }

  #[Test]
  public function itRejectsALongitudeBelowNegativeOneEightyDegrees(): void
  {
    try {
      new FacilityRowFactory()->map(self::ORGANIZATION_ID, [
        'type' => 'site',
        'name' => 'Main site',
        'latitude' => '48.8566',
        'longitude' => '-180.1',
      ]);
      self::fail('Expected an ImportRowValidationException.');
    } catch (ImportRowValidationException $exception) {
      self::assertSame('invalid', $exception->errorCode);
      self::assertSame('longitude', $exception->column);
    }
  }

  #[Test]
  public function itAcceptsCoordinatesAtTheInclusiveRangeBoundaries(): void
  {
    $request = new FacilityRowFactory()->map(self::ORGANIZATION_ID, [
      'type' => 'site',
      'name' => 'Main site',
      'latitude' => '-90',
      'longitude' => '180',
    ]);

    self::assertSame(-90.0, $request->latitude);
    self::assertSame(180.0, $request->longitude);
  }
}
