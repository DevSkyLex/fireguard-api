<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use Equipment\Application\Contract\Provisioning\ProvisionEquipmentRequest;
use Import\Application\Service\EquipmentRowFactory;
use Import\Domain\Exception\ImportRowValidationException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test EquipmentRowFactoryTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentRowFactory::class)]
final class EquipmentRowFactoryTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f66a01';

  #[Test]
  public function itMapsAFullyPopulatedRow(): void
  {
    $request = new EquipmentRowFactory()->map(self::ORGANIZATION_ID, [
      'type' => 'fire_extinguisher',
      'subType' => 'CO2',
      'brand' => 'Acme',
      'model' => 'X100',
      'serialNumber' => 'SN-1',
      'locationLabel' => 'Corridor A',
    ]);

    self::assertInstanceOf(ProvisionEquipmentRequest::class, $request);
    self::assertSame(self::ORGANIZATION_ID, $request->organizationId);
    self::assertSame('fire_extinguisher', $request->type);
    self::assertSame('CO2', $request->subType);
    self::assertSame('Acme', $request->brand);
    self::assertSame('X100', $request->model);
    self::assertSame('SN-1', $request->serialNumber);
    self::assertSame('Corridor A', $request->locationLabel);
  }

  #[Test]
  public function itTrimsValuesAndTreatsBlankOptionalColumnsAsNull(): void
  {
    $request = new EquipmentRowFactory()->map(self::ORGANIZATION_ID, [
      'type' => '  fire_extinguisher  ',
      'subType' => '',
      'brand' => '   ',
    ]);

    self::assertSame('fire_extinguisher', $request->type);
    self::assertNull($request->subType);
    self::assertNull($request->brand);
    self::assertNull($request->model);
    self::assertNull($request->serialNumber);
    self::assertNull($request->locationLabel);
  }

  #[Test]
  public function itRejectsAMissingRequiredTypeColumn(): void
  {
    $this->expectException(ImportRowValidationException::class);

    new EquipmentRowFactory()->map(self::ORGANIZATION_ID, ['subType' => 'CO2']);
  }

  #[Test]
  public function itRejectsABlankRequiredTypeColumn(): void
  {
    try {
      new EquipmentRowFactory()->map(self::ORGANIZATION_ID, ['type' => '   ']);
      self::fail('Expected an ImportRowValidationException.');
    } catch (ImportRowValidationException $exception) {
      self::assertSame('missing_required', $exception->errorCode);
      self::assertSame('type', $exception->column);
    }
  }

  #[Test]
  public function itDoesNotValidateEquipmentTypeEnumMembership(): void
  {
    // "not_a_real_type" is structurally valid (non-blank); enum membership is
    // left to CreateEquipmentHandler via EquipmentProvisioningPort, which
    // reports it as a non-fatal INVALID outcome instead.
    $request = new EquipmentRowFactory()->map(self::ORGANIZATION_ID, ['type' => 'not_a_real_type']);

    self::assertSame('not_a_real_type', $request->type);
  }
}
