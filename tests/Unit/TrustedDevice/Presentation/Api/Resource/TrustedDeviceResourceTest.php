<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Presentation\Api\Resource;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TrustedDevice\Presentation\Api\Resource\TrustedDeviceResource;

/**
 * Test TrustedDeviceResourceTest.
 *
 * @category Resource Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TrustedDeviceResource::class)]
final class TrustedDeviceResourceTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testResourceCanBeInstantiated(): void
  {
    self::assertInstanceOf(TrustedDeviceResource::class, new TrustedDeviceResource());
  }
  // #endregion
}
