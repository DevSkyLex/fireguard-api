<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Catalog;

use Authorization\Infrastructure\Catalog\RoleCatalog;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RoleCatalogTest.
 *
 * @category Catalog Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RoleCatalog::class)]
final class RoleCatalogTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuperAdminIncludesWildcard(): void
  {
    $permissions = RoleCatalog::superAdminPermissionNames();

    self::assertContains('*.*', $permissions);
  }
  // #endregion
}
