<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Domain\Model\Currency;

use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use MaintenanceCost\Domain\Model\Currency\MaintenanceCurrency;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Currency capture locks preserve the unit of historical financial facts. */
final class MaintenanceCurrencyTest extends TestCase
{
  #[Test]
  public function configuresBeforeTheFirstFactAndReplaysTheLockedValue(): void
  {
    self::assertSame('USD', new MaintenanceCurrency('EUR')->configure('USD')->currency);
    self::assertSame('EUR', new MaintenanceCurrency('EUR', true)->configure('EUR')->currency);
  }

  #[Test]
  public function refusesToConvertAlreadyCapturedFacts(): void
  {
    $this->expectException(MaintenanceCostException::class);
    $this->expectExceptionMessage('locked');
    new MaintenanceCurrency('EUR', true)->configure('USD');
  }

  #[Test]
  public function rejectsMalformedCurrencyCodes(): void
  {
    $this->expectException(MaintenanceCostException::class);
    new MaintenanceCurrency('eu');
  }
}
