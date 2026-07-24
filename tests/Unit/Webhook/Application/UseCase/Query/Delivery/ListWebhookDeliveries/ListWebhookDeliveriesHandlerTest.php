<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\UseCase\Query\Delivery\ListWebhookDeliveries;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Application\Port\Outbound\{WebhookDeliveryRepositoryPort, WebhookSubscriptionRepositoryPort};
use Webhook\Application\UseCase\Query\Delivery\ListWebhookDeliveries\{ListWebhookDeliveriesHandler, ListWebhookDeliveriesQuery, ListWebhookDeliveriesResult};
use Webhook\Domain\Exception\WebhookSubscriptionNotFoundException;
use Webhook\Domain\Model\Delivery\WebhookDelivery;
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookSubscriptionId};

/**
 * Test ListWebhookDeliveriesHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListWebhookDeliveriesHandler::class)]
final class ListWebhookDeliveriesHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string DELIVERY_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a03';

  private const string USER_ID = 'user-1';

  #[Test]
  public function itReturnsAPageOfDeliveryViews(): void
  {
    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->method('findById')->willReturn($this->subscription());

    $deliveryRepository = $this->createStub(WebhookDeliveryRepositoryPort::class);
    $deliveryRepository->method('listBySubscription')->willReturn([$this->delivery()]);
    $deliveryRepository->method('countBySubscription')->willReturn(1);

    $handler = new ListWebhookDeliveriesHandler(
      $subscriptionRepository,
      $deliveryRepository,
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $result = $handler->__invoke(new ListWebhookDeliveriesQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
      status: 'pending',
      page: 1,
      itemsPerPage: 30,
    ));

    self::assertInstanceOf(ListWebhookDeliveriesResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertSame(self::DELIVERY_ID, $result->items[0]->id);
    self::assertSame('intervention.published', $result->items[0]->eventType);
    self::assertSame('pending', $result->items[0]->status);
    self::assertSame(1, $result->total);
  }

  #[Test]
  public function itThrowsWhenTheSubscriptionIsMissing(): void
  {
    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->method('findById')->willReturn(null);

    $handler = new ListWebhookDeliveriesHandler(
      $subscriptionRepository,
      $this->createStub(WebhookDeliveryRepositoryPort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $this->expectException(WebhookSubscriptionNotFoundException::class);

    $handler->__invoke(new ListWebhookDeliveriesQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
    ));
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

  /**
   * Method delivery.
   *
   * @return WebhookDelivery a delivery aggregate under test
   */
  private function delivery(): WebhookDelivery
  {
    return WebhookDelivery::create(
      id: WebhookDeliveryId::fromString(self::DELIVERY_ID),
      subscriptionId: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: self::ORGANIZATION_ID,
      eventType: 'intervention.published',
      eventId: 'event-1',
      payload: ['foo' => 'bar'],
    );
  }
}
