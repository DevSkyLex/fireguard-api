<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Presentation\Api\Resource;

use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;
use Tenant\Presentation\Api\Resource\TenantResource;

/**
 * Test TenantResourceTest.
 *
 * @category Resource Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversNothing]
final class TenantResourceTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testResourceCanBeInstantiated(): void
  {
    self::assertInstanceOf(TenantResource::class, new TenantResource());
  }
  // #endregion
}
