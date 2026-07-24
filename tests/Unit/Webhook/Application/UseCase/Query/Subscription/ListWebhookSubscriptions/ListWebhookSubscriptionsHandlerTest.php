<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\UseCase\Query\Subscription\ListWebhookSubscriptions;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Application\Port\Outbound\WebhookSubscriptionRepositoryPort;
use Webhook\Application\UseCase\Query\Subscription\ListWebhookSubscriptions\{ListWebhookSubscriptionsHandler, ListWebhookSubscriptionsQuery, ListWebhookSubscriptionsResult};
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

/**
 * Test ListWebhookSubscriptionsHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListWebhookSubscriptionsHandler::class)]
final class ListWebhookSubscriptionsHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string USER_ID = 'user-1';

  #[Test]
  public function itReturnsAPageOfSubscriptionViews(): void
  {
    $repository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $repository->method('listByOrganization')->willReturn([$this->subscription()]);
    $repository->method('countByOrganization')->willReturn(1);

    $handler = new ListWebhookSubscriptionsHandler(
      $repository,
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $result = $handler->__invoke(new ListWebhookSubscriptionsQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      page: 1,
      itemsPerPage: 30,
    ));

    self::assertInstanceOf(ListWebhookSubscriptionsResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertSame(self::SUBSCRIPTION_ID, $result->items[0]->id);
    self::assertSame(1, $result->page);
    self::assertSame(30, $result->itemsPerPage);
    self::assertSame(1, $result->total);
  }

  #[Test]
  public function itClampsANonPositiveItemsPerPageToOne(): void
  {
    $repository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $repository->method('listByOrganization')->willReturn([]);
    $repository->method('countByOrganization')->willReturn(0);

    $handler = new ListWebhookSubscriptionsHandler(
      $repository,
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $result = $handler->__invoke(new ListWebhookSubscriptionsQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      page: 1,
      itemsPerPage: 0,
    ));

    self::assertSame(1, $result->itemsPerPage);
    self::assertSame([], $result->items);
    self::assertSame(0, $result->total);
  }

  /**
   * Method subscription.
   *
   * @return WebhookSubscription a subscription aggregate under test
   */
  private function subscription(): WebhookSubscription
  {
    return WebhookSubscription::create(
      id: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: self::ORGANIZATION_ID,
      url: 'https://example.com/hook',
      secretCiphertext: 'CIPHER',
      eventTypes: ['intervention.published'],
    );
  }
}
