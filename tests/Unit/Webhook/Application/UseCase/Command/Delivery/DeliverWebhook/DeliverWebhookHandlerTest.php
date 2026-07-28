<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\UseCase\Command\Delivery\DeliverWebhook;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\{LoggerInterface, NullLogger};
use Shared\Application\Message\VoidResult;
use Webhook\Application\Contract\Http\WebhookHttpResponse;
use Webhook\Application\Port\Outbound\{WebhookDeliveryRepositoryPort, WebhookHttpClientPort, WebhookSecretCipherPort, WebhookSubscriptionRepositoryPort};
use Webhook\Application\UseCase\Command\Delivery\DeliverWebhook\{DeliverWebhookCommand, DeliverWebhookHandler};
use Webhook\Domain\Exception\WebhookDeliveryAttemptFailedException;
use Webhook\Domain\Model\Delivery\WebhookDelivery;
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookDeliveryStatus, WebhookSubscriptionId};

use function hash_hmac;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Test DeliverWebhookHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeliverWebhookHandler::class)]
final class DeliverWebhookHandlerTest extends TestCase
{
  private const string DELIVERY_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a03';

  private const string SECRET = 'whsec_test-secret-value';

  #[Test]
  public function itMarksDeliveredAndSignsCorrectlyOnA2xxResponse(): void
  {
    $delivery = $this->pendingDelivery(attempts: 0);
    $subscription = $this->subscription();

    $deliveryRepository = $this->createMock(WebhookDeliveryRepositoryPort::class);
    $deliveryRepository->method('findById')->willReturn($delivery);
    $deliveryRepository->expects(self::once())->method('save')->with($delivery);

    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->method('findById')->willReturn($subscription);

    $cipher = $this->createStub(WebhookSecretCipherPort::class);
    $cipher->method('decrypt')->willReturn(self::SECRET);

    $capturedHeaders = null;
    $capturedBody = null;

    $httpClient = $this->createMock(WebhookHttpClientPort::class);
    $httpClient->expects(self::once())
      ->method('post')
      ->willReturnCallback(function (string $url, array $headers, string $body, int $timeout) use (&$capturedHeaders, &$capturedBody): WebhookHttpResponse {
        self::assertSame('https://example.com/hook', $url);
        self::assertSame(10, $timeout);
        $capturedHeaders = $headers;
        $capturedBody = $body;

        return new WebhookHttpResponse(200);
      });

    $handler = new DeliverWebhookHandler(
      $deliveryRepository,
      $subscriptionRepository,
      $cipher,
      $httpClient,
      new NullLogger(),
      timeoutSeconds: 10,
    );

    $handler->__invoke(new DeliverWebhookCommand(self::DELIVERY_ID));

    self::assertSame(WebhookDeliveryStatus::DELIVERED, $delivery->status());
    self::assertSame(200, $delivery->httpStatus());
    self::assertSame(1, $delivery->attempts());
    self::assertNotNull($delivery->deliveredAt());

    self::assertIsArray($capturedHeaders);
    self::assertSame('application/json', $capturedHeaders['Content-Type']);
    self::assertSame(self::DELIVERY_ID, $capturedHeaders['X-FireGuard-Webhook-Id']);
    self::assertSame('intervention.published', $capturedHeaders['X-FireGuard-Webhook-Event']);
    self::assertArrayHasKey('X-FireGuard-Webhook-Timestamp', $capturedHeaders);
    self::assertArrayHasKey('X-FireGuard-Webhook-Signature', $capturedHeaders);

    self::assertIsString($capturedBody);
    self::assertSame(json_encode(['interventionId' => 'i-1'], JSON_THROW_ON_ERROR), $capturedBody);

    $timestamp = $capturedHeaders['X-FireGuard-Webhook-Timestamp'];
    self::assertIsString($timestamp);
    $expectedSignature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $capturedBody, self::SECRET);
    self::assertSame($expectedSignature, $capturedHeaders['X-FireGuard-Webhook-Signature']);
  }

  #[Test]
  public function itIsIdempotentWhenTheDeliveryIsAlreadyDelivered(): void
  {
    $delivery = $this->pendingDelivery(attempts: 1);
    $delivery->markDelivered(200, new DateTimeImmutable());

    $deliveryRepository = $this->createMock(WebhookDeliveryRepositoryPort::class);
    $deliveryRepository->method('findById')->willReturn($delivery);
    $deliveryRepository->expects(self::never())->method('save');

    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $cipher = $this->createStub(WebhookSecretCipherPort::class);

    $httpClient = $this->createMock(WebhookHttpClientPort::class);
    $httpClient->expects(self::never())->method('post');

    $handler = new DeliverWebhookHandler($deliveryRepository, $subscriptionRepository, $cipher, $httpClient, new NullLogger());

    $handler->__invoke(new DeliverWebhookCommand(self::DELIVERY_ID));
  }

  #[Test]
  public function itRecordsARetryableFailureAndThrowsWhenAttemptsRemain(): void
  {
    $delivery = $this->pendingDelivery(attempts: 1);

    $deliveryRepository = $this->createMock(WebhookDeliveryRepositoryPort::class);
    $deliveryRepository->method('findById')->willReturn($delivery);
    $deliveryRepository->expects(self::once())->method('save')->with($delivery);

    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->method('findById')->willReturn($this->subscription());

    $cipher = $this->createStub(WebhookSecretCipherPort::class);
    $cipher->method('decrypt')->willReturn(self::SECRET);

    $httpClient = $this->createStub(WebhookHttpClientPort::class);
    $httpClient->method('post')->willReturn(new WebhookHttpResponse(500));

    $handler = new DeliverWebhookHandler($deliveryRepository, $subscriptionRepository, $cipher, $httpClient, new NullLogger());

    $this->expectException(WebhookDeliveryAttemptFailedException::class);

    $handler->__invoke(new DeliverWebhookCommand(self::DELIVERY_ID));

    // Not reached due to expectException, but documents post-conditions:
    self::assertSame(WebhookDeliveryStatus::PENDING, $delivery->status());
    self::assertSame(2, $delivery->attempts());
  }

  #[Test]
  public function itMarksTerminallyFailedOnceTheMaxAttemptCountIsReached(): void
  {
    // MAX_ATTEMPTS is 5; attempts=4 means this is the 5th (terminal) attempt.
    $delivery = $this->pendingDelivery(attempts: 4);

    $deliveryRepository = $this->createMock(WebhookDeliveryRepositoryPort::class);
    $deliveryRepository->method('findById')->willReturn($delivery);
    $deliveryRepository->expects(self::once())->method('save')->with($delivery);

    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->method('findById')->willReturn($this->subscription());

    $cipher = $this->createStub(WebhookSecretCipherPort::class);
    $cipher->method('decrypt')->willReturn(self::SECRET);

    $httpClient = $this->createStub(WebhookHttpClientPort::class);
    $httpClient->method('post')->willReturn(new WebhookHttpResponse(503));

    $handler = new DeliverWebhookHandler($deliveryRepository, $subscriptionRepository, $cipher, $httpClient, new NullLogger());

    // No exception — a terminal failure is recorded and returns normally.
    $handler->__invoke(new DeliverWebhookCommand(self::DELIVERY_ID));

    self::assertSame(WebhookDeliveryStatus::FAILED, $delivery->status());
    self::assertSame(5, $delivery->attempts());
    self::assertSame(503, $delivery->httpStatus());
  }

  #[Test]
  public function itMarksFailedWhenTheSubscriptionNoLongerExists(): void
  {
    $delivery = $this->pendingDelivery(attempts: 0);

    $deliveryRepository = $this->createMock(WebhookDeliveryRepositoryPort::class);
    $deliveryRepository->method('findById')->willReturn($delivery);
    $deliveryRepository->expects(self::once())->method('save')->with($delivery);

    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->method('findById')->willReturn(null);

    $cipher = $this->createStub(WebhookSecretCipherPort::class);
    $httpClient = $this->createMock(WebhookHttpClientPort::class);
    $httpClient->expects(self::never())->method('post');

    $handler = new DeliverWebhookHandler($deliveryRepository, $subscriptionRepository, $cipher, $httpClient, new NullLogger());

    $handler->__invoke(new DeliverWebhookCommand(self::DELIVERY_ID));

    self::assertSame(WebhookDeliveryStatus::FAILED, $delivery->status());
  }

  #[Test]
  public function itLogsAndNoOpsWhenTheDeliveryNoLongerExists(): void
  {
    $deliveryRepository = $this->createMock(WebhookDeliveryRepositoryPort::class);
    $deliveryRepository->method('findById')->willReturn(null);
    $deliveryRepository->expects(self::never())->method('save');

    $subscriptionRepository = $this->createMock(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->expects(self::never())->method('findById');

    $httpClient = $this->createMock(WebhookHttpClientPort::class);
    $httpClient->expects(self::never())->method('post');

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('error')
      ->with(
        'Webhook delivery not found for delivery attempt.',
        ['delivery_id' => self::DELIVERY_ID],
      );

    $handler = new DeliverWebhookHandler(
      $deliveryRepository,
      $subscriptionRepository,
      $this->createStub(WebhookSecretCipherPort::class),
      $httpClient,
      $logger,
    );

    self::assertInstanceOf(VoidResult::class, $handler->__invoke(new DeliverWebhookCommand(self::DELIVERY_ID)));
  }

  /**
   * Method pendingDelivery.
   *
   * @param int $attempts the number of attempts already recorded
   *
   * @return WebhookDelivery a pending delivery fixture
   */
  private function pendingDelivery(int $attempts): WebhookDelivery
  {
    $now = new DateTimeImmutable('2026-07-18T09:00:00+00:00');

    return WebhookDelivery::reconstitute(
      id: WebhookDeliveryId::fromString(self::DELIVERY_ID),
      subscriptionId: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: self::ORGANIZATION_ID,
      eventType: 'intervention.published',
      eventId: 'event-1',
      payload: ['interventionId' => 'i-1'],
      status: WebhookDeliveryStatus::PENDING,
      attempts: $attempts,
      createdAt: $now,
      updatedAt: $now,
      httpStatus: null,
      lastError: null,
      nextRetryAt: null,
      deliveredAt: null,
    );
  }

  /**
   * Method subscription.
   *
   * @return WebhookSubscription an active subscription fixture
   */
  private function subscription(): WebhookSubscription
  {
    return WebhookSubscription::reconstitute(
      id: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: self::ORGANIZATION_ID,
      url: 'https://example.com/hook',
      secretCiphertext: 'ciphertext',
      eventTypes: ['intervention.published'],
      isActive: true,
      description: '',
      createdAt: new DateTimeImmutable(),
      updatedAt: new DateTimeImmutable(),
    );
  }
}
