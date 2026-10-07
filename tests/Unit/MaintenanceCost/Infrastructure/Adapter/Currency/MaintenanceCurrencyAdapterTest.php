<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Infrastructure\Adapter\Currency;

use Doctrine\DBAL\Connection;
use LogicException;
use MaintenanceCost\Infrastructure\Adapter\Currency\MaintenanceCurrencyAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** A currency capture cannot commit separately from its caller's physical or financial fact. */
final class MaintenanceCurrencyAdapterTest extends TestCase
{
  #[Test]
  public function refusesAnUnownedTransactionBeforeWriting(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::never())->method('executeStatement');
    $this->expectException(LogicException::class);
    new MaintenanceCurrencyAdapter($connection)->lock('650e8400-e29b-41d4-a716-448040000001');
  }
}
