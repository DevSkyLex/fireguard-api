<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Application\UseCase\Command\StartPortal;

use Billing\Application\Port\Outbound\{StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Application\UseCase\Command\StartPortal\{
  StartPortalCommand,
  StartPortalHandler,
  StartPortalResult
};
use Billing\Domain\Exception\BillingCustomerNotFoundException;
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\SubscriptionId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test StartPortalHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class StartPortalHandlerTest extends TestCase
{
  private const string SUBSCRIPTION_ID = '44444444-4444-4444-8444-444444444444';

  #[Test]
  public function itOpensPortalSessionAndReturnsUrl(): void
  {
    $subscription = $this->subscription();

    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::once())
      ->method('createBillingPortalSession')
      ->with('cus_1', 'https://app.fireguard.local/organizations/org-1/settings?tab=subscription')
      ->willReturn('https://billing.stripe.com/session/abc');

    $subscriptions = $this->createStub(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn($subscription);

    $handler = new StartPortalHandler($subscriptions, $stripe, 'https://app.fireguard.local/');

    $result = $handler(new StartPortalCommand('org-1'));

    self::assertInstanceOf(StartPortalResult::class, $result);
    self::assertSame('https://billing.stripe.com/session/abc', $result->url);
  }

  #[Test]
  public function itBuildsReturnUrlWithoutDoubleSlashWhenFrontendUrlHasNoTrailingSlash(): void
  {
    $subscription = $this->subscription();

    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::once())
      ->method('createBillingPortalSession')
      ->with('cus_1', 'https://app.fireguard.local/organizations/org-1/settings?tab=subscription')
      ->willReturn('https://billing.stripe.com/session/xyz');

    $subscriptions = $this->createStub(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn($subscription);

    $handler = new StartPortalHandler($subscriptions, $stripe, 'https://app.fireguard.local');

    $result = $handler(new StartPortalCommand('org-1'));

    self::assertSame('https://billing.stripe.com/session/xyz', $result->url);
  }

  #[Test]
  public function itThrowsWhenOrganizationHasNoBillingCustomer(): void
  {
    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::never())->method('createBillingPortalSession');

    $subscriptions = $this->createStub(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn(null);

    $handler = new StartPortalHandler($subscriptions, $stripe, 'https://app.fireguard.local');

    $this->expectException(BillingCustomerNotFoundException::class);

    $handler(new StartPortalCommand('org-1'));
  }

  private function subscription(): Subscription
  {
    return Subscription::start(
      SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org-1',
      'cus_1',
    );
  }
}
