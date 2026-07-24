<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\UseCase\Command\Delivery\RedeliverWebhook;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Application\Port\Outbound\{WebhookDeliveryQueuePort, WebhookDeliveryRepositoryPort, WebhookSubscriptionRepositoryPort};
use Webhook\Application\UseCase\Command\Delivery\RedeliverWebhook\{RedeliverWebhookCommand, RedeliverWebhookHandler, RedeliverWebhookResult};
use Webhook\Domain\Exception\{WebhookDeliveryNotFoundException, WebhookSubscriptionNotFoundException};
use Webhook\Domain\Model\Delivery\WebhookDelivery;
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookSubscriptionId};

/**
 * Test RedeliverWebhookHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RedeliverWebhookHandler::class)]
final class RedeliverWebhookHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string DELIVERY_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a03';

  private const string USER_ID = 'user-1';

  #[Test]
  public function itReopensTheDeliveryAndReEnqueuesIt(): void
  {
    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->method('findById')->willReturn($this->subscription());

    $deliveryRepository = $this->createMock(WebhookDeliveryRepositoryPort::class);
    $deliveryRepository->method('findById')->willReturn($this->delivery());
    $deliveryRepository->expects(self::once())->method('save');

    $queue = $this->createMock(WebhookDeliveryQueuePort::class);
    $queue->expects(self::once())->method('dispatch')->with(self::DELIVERY_ID);

    $handler = new RedeliverWebhookHandler(
      $subscriptionRepository,
      $deliveryRepository,
      $queue,
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $result = $handler->__invoke(new RedeliverWebhookCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
      deliveryId: self::DELIVERY_ID,
    ));

    self::assertInstanceOf(RedeliverWebhookResult::class, $result);
    self::assertSame(self::DELIVERY_ID, $result->deliveryId);
    self::assertSame(self::SUBSCRIPTION_ID, $result->subscriptionId);
  }

  #[Test]
  public function itThrowsWhenTheSubscriptionIsMissing(): void
  {
    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->method('findById')->willReturn(null);

    $handler = new RedeliverWebhookHandler(
      $subscriptionRepository,
      $this->createStub(WebhookDeliveryRepositoryPort::class),
      $this->createStub(WebhookDeliveryQueuePort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $this->expectException(WebhookSubscriptionNotFoundException::class);

    $handler->__invoke(new RedeliverWebhookCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
      deliveryId: self::DELIVERY_ID,
    ));
  }

  #[Test]
  public function itThrowsWhenTheDeliveryIsMissing(): void
  {
    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->method('findById')->willReturn($this->subscription());

    $deliveryRepository = $this->createStub(WebhookDeliveryRepositoryPort::class);
    $deliveryRepository->method('findById')->willReturn(null);

    $handler = new RedeliverWebhookHandler(
      $subscriptionRepository,
      $deliveryRepository,
      $this->createStub(WebhookDeliveryQueuePort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    $this->expectException(WebhookDeliveryNotFoundException::class);

    $handler->__invoke(new RedeliverWebhookCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
      deliveryId: self::DELIVERY_ID,
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
