<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Resource;

use Authorization\Presentation\Api\Resource\{PermissionResource, RoleResource};
use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AuthorizationResourcesTest.
 *
 * @category Resource Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversNothing]
final class AuthorizationResourcesTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testResourcesCanBeInstantiated(): void
  {
    self::assertInstanceOf(RoleResource::class, new RoleResource());
    self::assertInstanceOf(PermissionResource::class, new PermissionResource());
  }
  // #endregion
}
