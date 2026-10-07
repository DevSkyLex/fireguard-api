<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Infrastructure\Adapter\MaintenanceExport;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Adapter\MaintenanceExport\MaintenanceExportSiteIdentityAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class MaintenanceExportSiteIdentityAdapterTest
 *
 * Proves composite identity fall-through never queries another resource owner's database table.
 *
 * @category Unit Tests
 */
#[CoversClass(MaintenanceExportSiteIdentityAdapter::class)]
final class MaintenanceExportSiteIdentityAdapterTest extends TestCase
{
  #[Test]
  public function unsupportedResourceTypesDoNotAccessDoctrine(): void
  {
    $main = $this->createMock(EntityManagerInterface::class);
    $main->expects(self::never())->method('getConnection');
    $adapter = new MaintenanceExportSiteIdentityAdapter($main);

    foreach (['customer', 'equipment', 'SITE', 'unknown'] as $type) {
      self::assertFalse($adapter->exists(
        'd17e8400-e29b-41d4-a716-446655790001',
        $type,
        'd17e8400-e29b-41d4-a716-446655790002',
      ));
    }
  }
}
