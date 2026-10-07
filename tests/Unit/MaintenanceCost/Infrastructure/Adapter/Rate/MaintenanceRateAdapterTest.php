<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Infrastructure\Adapter\Rate;

use Doctrine\DBAL\Connection;
use LogicException;
use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;
use MaintenanceCost\Infrastructure\Adapter\Rate\MaintenanceRateAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Appending rates requires the organization-scoped transaction used for uniqueness and replay. */
final class MaintenanceRateAdapterTest extends TestCase
{
  #[Test]
  public function refusesAnAppendOutsideTheOwningTransaction(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::never())->method('insert');
    $this->expectException(LogicException::class);
    new MaintenanceRateAdapter($connection)->append('organization', 'client', new MaintenanceRateSnapshot('rate', 'member', '1.000000', 'EUR', '2026-01-01'));
  }
}
