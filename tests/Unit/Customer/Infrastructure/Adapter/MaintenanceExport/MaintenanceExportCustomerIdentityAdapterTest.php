<?php

declare(strict_types=1);

namespace Tests\Unit\Customer\Infrastructure\Adapter\MaintenanceExport;

use Customer\Infrastructure\Adapter\MaintenanceExport\MaintenanceExportCustomerIdentityAdapter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class MaintenanceExportCustomerIdentityAdapterTest
 *
 * Proves composite identity fall-through never queries another resource owner's database table.
 *
 * @category Unit Tests
 */
#[CoversClass(MaintenanceExportCustomerIdentityAdapter::class)]
final class MaintenanceExportCustomerIdentityAdapterTest extends TestCase
{
  #[Test]
  public function unsupportedResourceTypesDoNotAccessDoctrine(): void
  {
    $main = $this->createMock(EntityManagerInterface::class);
    $main->expects(self::never())->method('getConnection');
    $adapter = new MaintenanceExportCustomerIdentityAdapter($main);

    foreach (['site', 'equipment', 'CUSTOMER', 'unknown'] as $type) {
      self::assertFalse($adapter->exists(
        'd17e8400-e29b-41d4-a716-446655790001',
        $type,
        'd17e8400-e29b-41d4-a716-446655790002',
      ));
    }
  }
}
