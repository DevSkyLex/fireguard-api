<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\Contract\Provisioning;

use Facility\Application\Contract\Provisioning\ProvisionFacilityRequest;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ProvisionFacilityRequest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ProvisionFacilityRequest::class)]
final class ProvisionFacilityRequestTest extends TestCase
{
  #[Test]
  public function testConstructorRoundTripsAllProperties(): void
  {
    $request = new ProvisionFacilityRequest(
      organizationId: 'org-1',
      type: 'building',
      name: 'HQ',
      code: 'HQ-01',
      address: '1 Main St',
      latitude: 48.85,
      longitude: 2.35,
      parentCode: 'SITE-01',
    );

    self::assertSame('org-1', $request->organizationId);
    self::assertSame('building', $request->type);
    self::assertSame('HQ', $request->name);
    self::assertSame('HQ-01', $request->code);
    self::assertSame('1 Main St', $request->address);
    self::assertSame(48.85, $request->latitude);
    self::assertSame(2.35, $request->longitude);
    self::assertSame('SITE-01', $request->parentCode);
  }

  #[Test]
  public function testConstructorAppliesNullDefaults(): void
  {
    $request = new ProvisionFacilityRequest(
      organizationId: 'org-1',
      type: 'site',
      name: 'Root Site',
    );

    self::assertNull($request->code);
    self::assertNull($request->address);
    self::assertNull($request->latitude);
    self::assertNull($request->longitude);
    self::assertNull($request->parentCode);
  }
}
