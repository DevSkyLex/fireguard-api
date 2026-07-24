<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\UseCase\Command\Subscription\PingWebhookSubscription;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Webhook\Application\Port\Outbound\{WebhookDeliveryQueuePort, WebhookDeliveryRepositoryPort, WebhookSubscriptionRepositoryPort};
use Webhook\Application\UseCase\Command\Subscription\PingWebhookSubscription\{PingWebhookSubscriptionCommand, PingWebhookSubscriptionHandler, PingWebhookSubscriptionResult};
use Webhook\Domain\Exception\{WebhookSubscriptionNotFoundException, WebhookValidationException};
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

/**
 * Test PingWebhookSubscriptionHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PingWebhookSubscriptionHandler::class)]
final class PingWebhookSubscriptionHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string DELIVERY_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a03';

  private const string USER_ID = 'user-1';

  #[Test]
  public function itReservesAndEnqueuesATestDelivery(): void
  {
    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->method('findById')->willReturn($this->subscription());

    $deliveryRepository = $this->createStub(WebhookDeliveryRepositoryPort::class);
    $deliveryRepository->method('reserve')->willReturn(true);

    $queue = $this->createMock(WebhookDeliveryQueuePort::class);
    $queue->expects(self::once())->method('dispatch')->with(self::DELIVERY_ID);

    $handler = new PingWebhookSubscriptionHandler(
      $subscriptionRepository,
      $deliveryRepository,
      $queue,
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->uuidFactory(),
    );

    $result = $handler->__invoke(new PingWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
    ));

    self::assertInstanceOf(PingWebhookSubscriptionResult::class, $result);
    self::assertSame(self::DELIVERY_ID, $result->deliveryId);
    self::assertSame(self::SUBSCRIPTION_ID, $result->subscriptionId);
  }

  #[Test]
  public function itThrowsWhenTheReservationFails(): void
  {
    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->method('findById')->willReturn($this->subscription());

    $deliveryRepository = $this->createStub(WebhookDeliveryRepositoryPort::class);
    $deliveryRepository->method('reserve')->willReturn(false);

    $handler = new PingWebhookSubscriptionHandler(
      $subscriptionRepository,
      $deliveryRepository,
      $this->createStub(WebhookDeliveryQueuePort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->uuidFactory(),
    );

    $this->expectException(WebhookValidationException::class);

    $handler->__invoke(new PingWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
    ));
  }

  #[Test]
  public function itThrowsWhenTheSubscriptionIsMissing(): void
  {
    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->method('findById')->willReturn(null);

    $handler = new PingWebhookSubscriptionHandler(
      $subscriptionRepository,
      $this->createStub(WebhookDeliveryRepositoryPort::class),
      $this->createStub(WebhookDeliveryQueuePort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->uuidFactory(),
    );

    $this->expectException(WebhookSubscriptionNotFoundException::class);

    $handler->__invoke(new PingWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
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
   * Method uuidFactory.
   *
   * @return UuidFactory a uuid factory generating the delivery identifier under test
   */
  private function uuidFactory(): UuidFactory
  {
    $generator = $this->createStub(UuidGeneratorPort::class);
    $generator->method('generate')->willReturn(self::DELIVERY_ID);

    return new UuidFactory($generator);
  }
}
