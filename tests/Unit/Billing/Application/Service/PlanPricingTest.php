<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Application\Service;

use Billing\Application\Service\PlanPricing;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test PlanPricingTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PlanPricing::class)]
final class PlanPricingTest extends TestCase
{
  #[Test]
  public function testConstructorExposesEveryField(): void
  {
    $pricing = new PlanPricing(
      planKey: 'pro',
      currency: 'eur',
      monthlyAmount: 2900,
      yearlyAmount: 29000,
    );

    self::assertSame('pro', $pricing->planKey);
    self::assertSame('eur', $pricing->currency);
    self::assertSame(2900, $pricing->monthlyAmount);
    self::assertSame(29000, $pricing->yearlyAmount);
  }

  #[Test]
  public function testAnUnofferedCadenceIsRepresentedByANullAmount(): void
  {
    $pricing = new PlanPricing('max', 'usd', null, 99000);

    self::assertNull($pricing->monthlyAmount);
    self::assertSame(99000, $pricing->yearlyAmount);
  }
}
