<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Domain\Model\Rate;

use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use MaintenanceCost\Domain\Model\Rate\MaintenanceHourlyRate;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/** Exact rate validation is independent from HTTP and persistence. */
final class MaintenanceHourlyRateTest extends TestCase
{
  private const string MEMBER = '650e8400-e29b-41d4-a716-448040000004';

  private const string CLIENT = '650e8400-e29b-41d4-a716-448040000010';

  #[Test]
  public function preservesSixDecimalPrecisionAndKnownZero(): void
  {
    self::assertSame('42.000001', new MaintenanceHourlyRate(self::MEMBER, '42.000001', '2028-02-29', self::CLIENT)->hourlyAmount);
    self::assertSame('0.000000', new MaintenanceHourlyRate(self::MEMBER, '0', '2026-01-01', self::CLIENT)->hourlyAmount);
  }

  /**
   * @return iterable<string,array{string,string,string,string}>
   */
  public static function invalidRates(): iterable
  {
    yield 'negative' => [self::MEMBER, '-1', '2026-01-01', self::CLIENT];
    yield 'excess precision' => [self::MEMBER, '1.0000001', '2026-01-01', self::CLIENT];
    yield 'scientific notation' => [self::MEMBER, '1e2', '2026-01-01', self::CLIENT];
    yield 'nonexistent calendar date' => [self::MEMBER, '1', '2026-02-29', self::CLIENT];
    yield 'invalid member identity' => ['member', '1', '2026-01-01', self::CLIENT];
    yield 'invalid client identity' => [self::MEMBER, '1', '2026-01-01', 'client'];
  }

  #[Test]
  #[DataProvider('invalidRates')]
  public function refusesInvalidParameters(string $member, string $amount, string $date, string $client): void
  {
    $this->expectException(MaintenanceCostException::class);
    new MaintenanceHourlyRate($member, $amount, $date, $client);
  }
}
