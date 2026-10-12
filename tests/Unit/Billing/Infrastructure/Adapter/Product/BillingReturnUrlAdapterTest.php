<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Infrastructure\Adapter\Product;

use Billing\Infrastructure\Adapter\Product\BillingReturnUrlAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Tests\Support\Shared\ProductConfigFixture;

/**
 * Class BillingReturnUrlAdapterTest
 *
 * @category Test
 */
#[CoversClass(BillingReturnUrlAdapter::class)]
final class BillingReturnUrlAdapterTest extends TestCase
{
  #[Test]
  public function preservesAllExistingBillingReturnUrls(): void
  {
    $urls = new BillingReturnUrlAdapter(
      ProductConfigFixture::create(['product.frontend_url' => 'https://app.fireguard.test/']),
      ProductConfigFixture::parameters()['product.billing_settings_path'],
    );

    self::assertSame('https://app.fireguard.test/organizations/org-42/settings?tab=subscription', $urls->portal('org-42'));
    self::assertSame('https://app.fireguard.test/organizations/org-42/settings?tab=subscription&checkout=cancel', $urls->checkoutCancel('org-42'));
    self::assertSame('https://app.fireguard.test/organizations/org-42/settings?tab=subscription&checkout=success&checkoutPlan=pro&checkoutInterval=month', $urls->checkoutSuccess('org-42', 'pro', 'month'));
  }

  #[Test]
  public function usesTheConfiguredRouteAndEncodesIdentifiersAsData(): void
  {
    $urls = new BillingReturnUrlAdapter(
      ProductConfigFixture::create(['product.frontend_url' => 'https://workshop.example.test/app/']),
      '/teams/{organizationId}/billing',
    );

    self::assertSame('https://workshop.example.test/app/teams/org%2F%3F%23%26/billing', $urls->portal('org/?#&'));
    self::assertSame('https://workshop.example.test/app/teams/org-42/billing?checkout=success&checkoutPlan=pro%20%26%20max&checkoutInterval=year', $urls->checkoutSuccess('org-42', 'pro & max', 'year'));
    self::assertSame('https://workshop.example.test/app/teams/org-42/billing?checkout=cancel', $urls->checkoutCancel('org-42'));
  }

  #[Test]
  #[DataProvider('invalidSettingsPaths')]
  public function rejectsUnsafeOrMalformedConfiguredRoutes(string $settingsPath): void
  {
    $this->expectException(InvalidArgumentException::class);

    new BillingReturnUrlAdapter(ProductConfigFixture::create(), $settingsPath);
  }

  /**
   * @return iterable<string, array{string}> invalid deployment route values
   */
  public static function invalidSettingsPaths(): iterable
  {
    yield 'absolute URL' => ['https://outside.example.test/{organizationId}'];
    yield 'protocol-relative URL' => ['//outside.example.test/{organizationId}'];
    yield 'relative without leading slash' => ['teams/{organizationId}/billing'];
    yield 'missing placeholder' => ['/billing'];
    yield 'duplicate placeholder' => ['/teams/{organizationId}/{organizationId}'];
    yield 'placeholder in query' => ['/billing?organization={organizationId}'];
    yield 'unexpected placeholder' => ['/teams/{organizationId}/{returnUrl}'];
    yield 'fragment' => ['/teams/{organizationId}/billing#outside'];
    yield 'backslash' => ['/\\outside.example.test/{organizationId}'];
    yield 'control character' => ["/teams/{organizationId}/billing\noutside"];
  }
}
