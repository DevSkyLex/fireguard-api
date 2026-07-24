<?php

declare(strict_types=1);

namespace Tests\Billing\Application\Service;

use Billing\Application\Service\BillingPriceCatalog;
use Billing\Domain\ValueObject\BillingInterval;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test BillingPriceCatalogTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class BillingPriceCatalogTest extends TestCase
{
  #[Test]
  public function itResolvesAPriceIdForAPlanAndInterval(): void
  {
    $catalog = new BillingPriceCatalog($this->prices(), 'eur');

    self::assertSame('price_pro_m', $catalog->priceIdFor('pro', BillingInterval::MONTH));
    self::assertSame('price_max_y', $catalog->priceIdFor('max', BillingInterval::YEAR));
  }

  #[Test]
  public function itReturnsNullForAnUnconfiguredPlan(): void
  {
    $catalog = new BillingPriceCatalog($this->prices(), 'eur');

    self::assertNull($catalog->priceIdFor('free', BillingInterval::MONTH));
  }

  #[Test]
  public function itReverseResolvesAPriceIdToPlanAndInterval(): void
  {
    $catalog = new BillingPriceCatalog($this->prices(), 'eur');

    $resolved = $catalog->resolve('price_pro_y');

    self::assertNotNull($resolved);
    self::assertSame('pro', $resolved['planKey']);
    self::assertSame(BillingInterval::YEAR, $resolved['interval']);
  }

  #[Test]
  public function itReturnsNullWhenReverseResolvingAnUnknownPrice(): void
  {
    $catalog = new BillingPriceCatalog($this->prices(), 'eur');

    self::assertNull($catalog->resolve('price_unknown'));
  }

  #[Test]
  public function itReportsPayablePlans(): void
  {
    $catalog = new BillingPriceCatalog($this->prices(), 'eur');

    self::assertTrue($catalog->isPayable('pro'));
    self::assertTrue($catalog->isPayable('max'));
    self::assertFalse($catalog->isPayable('free'));
  }

  #[Test]
  public function itExposesDisplayPricingForEveryPayablePlan(): void
  {
    $catalog = new BillingPriceCatalog($this->prices(), 'eur');

    $pricing = $catalog->pricing();

    self::assertCount(2, $pricing);
    self::assertSame('pro', $pricing[0]->planKey);
    self::assertSame('eur', $pricing[0]->currency);
    self::assertSame(1000, $pricing[0]->monthlyAmount);
    self::assertSame(10000, $pricing[0]->yearlyAmount);
  }

  #[Test]
  public function itIgnoresBlankPriceIds(): void
  {
    $catalog = new BillingPriceCatalog(
      ['pro' => ['month' => ['priceId' => '', 'amount' => 0]]],
      'eur',
    );

    self::assertNull($catalog->priceIdFor('pro', BillingInterval::MONTH));
    self::assertFalse($catalog->isPayable('pro'));
  }

  /**
   * @return array<string, mixed>
   */
  private function prices(): array
  {
    return [
      'pro' => [
        'month' => ['priceId' => 'price_pro_m', 'amount' => 1000],
        'year' => ['priceId' => 'price_pro_y', 'amount' => 10000],
      ],
      'max' => [
        'month' => ['priceId' => 'price_max_m', 'amount' => 5000],
        'year' => ['priceId' => 'price_max_y', 'amount' => 50000],
      ],
    ];
  }
}
