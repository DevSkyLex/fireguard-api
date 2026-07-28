<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Presentation\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use Billing\Application\Service\BillingPriceCatalog;
use Billing\Presentation\Api\Provider\GetPricingProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_column;

/**
 * Test GetPricingProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetPricingProvider::class)]
final class GetPricingProviderTest extends TestCase
{
  #[Test]
  public function testProvideExposesEveryPayablePlan(): void
  {
    $catalog = new BillingPriceCatalog([
      'free' => [],
      'pro' => [
        'month' => ['priceId' => 'price_pro_month', 'amount' => 2900],
        'year' => ['priceId' => 'price_pro_year', 'amount' => 29000],
      ],
      'max' => [
        'year' => ['priceId' => 'price_max_year', 'amount' => 99000],
      ],
    ], 'eur');

    $outputs = new GetPricingProvider($catalog)->provide(new GetCollection());

    self::assertSame(['pro', 'max'], array_column($outputs, 'planKey'));

    self::assertSame('eur', $outputs[0]->currency);
    self::assertSame(2900, $outputs[0]->monthlyAmount);
    self::assertSame(29000, $outputs[0]->yearlyAmount);

    self::assertNull($outputs[1]->monthlyAmount);
    self::assertSame(99000, $outputs[1]->yearlyAmount);
  }

  #[Test]
  public function testProvideReturnsAnEmptyListWhenNoPlanIsPayable(): void
  {
    self::assertSame([], new GetPricingProvider(new BillingPriceCatalog([], 'eur'))->provide(new GetCollection()));
  }
}
