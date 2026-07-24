<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\Contract\Provisioning;

use Equipment\Application\Contract\Provisioning\ProvisionEquipmentRequest;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ProvisionEquipmentRequestTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ProvisionEquipmentRequest::class)]
final class ProvisionEquipmentRequestTest extends TestCase
{
  #[Test]
  public function itExposesAllProperties(): void
  {
    $request = new ProvisionEquipmentRequest(
      organizationId: 'org-1',
      type: 'fire_extinguisher',
      subType: 'CO2',
      brand: 'Acme',
      model: 'X1',
      serialNumber: 'SN-1',
      locationLabel: 'Hall A',
    );

    self::assertSame('org-1', $request->organizationId);
    self::assertSame('fire_extinguisher', $request->type);
    self::assertSame('CO2', $request->subType);
    self::assertSame('Acme', $request->brand);
    self::assertSame('X1', $request->model);
    self::assertSame('SN-1', $request->serialNumber);
    self::assertSame('Hall A', $request->locationLabel);
  }

  #[Test]
  public function itDefaultsOptionalFieldsToNull(): void
  {
    $request = new ProvisionEquipmentRequest('org-1', 'sprinkler');

    self::assertNull($request->subType);
    self::assertNull($request->brand);
    self::assertNull($request->model);
    self::assertNull($request->serialNumber);
    self::assertNull($request->locationLabel);
  }
}
