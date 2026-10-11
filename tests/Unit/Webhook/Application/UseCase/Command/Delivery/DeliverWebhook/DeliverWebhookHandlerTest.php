<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\UseCase\Command\Delivery\DeliverWebhook;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\{MockObject, Stub};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{LoggerPort, TransactionManagerPort, UuidGeneratorPort};
use Webhook\Application\Contract\Http\WebhookHttpResponse;
use Webhook\Application\Port\Outbound\Internal\{WebhookDeliveryRepositoryPort, WebhookDeliveryWorkPort, WebhookSubscriptionRepositoryPort};
use Webhook\Application\Port\Outbound\{WebhookDeliverySchedulePort, WebhookHttpClientPort, WebhookSecretCipherPort};
use Webhook\Application\UseCase\Command\Delivery\DeliverWebhook\{DeliverWebhookCommand, DeliverWebhookHandler};
use Webhook\Domain\Model\Delivery\{RestoredWebhookDeliveryAttempt, RestoredWebhookDeliveryMetadata, WebhookDelivery};
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\Service\WebhookUrlPolicy;
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookDeliveryStatus, WebhookSubscriptionId};

use function hash_hmac;
use function json_encode;
use function strlen;
use function unserialize;

use const JSON_THROW_ON_ERROR;

/** Verifies HTTP budget, ownership, durable retry and historical generation identity. */
#[CoversClass(DeliverWebhookHandler::class)]
final class DeliverWebhookHandlerTest extends TestCase
{
  private const string DELIVERY_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string TOKEN = '018f0b68-6758-7a12-8a1d-3f0d97f64a03';

  private WebhookDeliveryWorkPort&MockObject $work;

  private WebhookDeliverySchedulePort&MockObject $schedule;

  private WebhookDeliveryRepositoryPort&Stub $repository;

  private WebhookSubscriptionRepositoryPort&Stub $subscriptions;

  private WebhookHttpClientPort&MockObject $http;

  private WebhookSecretCipherPort&Stub $cipher;

  protected function setUp(): void
  {
    $this->work = $this->createMock(WebhookDeliveryWorkPort::class);
    $this->schedule = $this->createMock(WebhookDeliverySchedulePort::class);
    $this->repository = $this->createStub(WebhookDeliveryRepositoryPort::class);
    $this->subscriptions = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $this->http = $this->createMock(WebhookHttpClientPort::class);
    $this->cipher = $this->createStub(WebhookSecretCipherPort::class);
  }

  #[Test]
  public function signsAndConfirmsTheOwnedAttempt(): void
  {
    $delivery = $this->delivery();
    $this->claimed($delivery);
    $this->work->expects(self::once())->method('startHttpAttempt')->with(self::DELIVERY_ID, 0, self::TOKEN)->willReturn(true);
    $this->http->expects(self::once())->method('post')->willReturnCallback(static function (string $url, array $headers, string $body, int $timeout): WebhookHttpResponse {
      self::assertSame('https://example.com/hook', $url);
      self::assertSame(10, $timeout);
      self::assertSame(self::DELIVERY_ID, $headers['X-Fireguard-Webhook-Id']);
      self::assertIsString($headers['X-Fireguard-Webhook-Timestamp']);
      self::assertSame('sha256=' . hash_hmac('sha256', $headers['X-Fireguard-Webhook-Timestamp'] . '.' . $body, 'secret'), $headers['X-Fireguard-Webhook-Signature']);
      self::assertSame(json_encode(['resourceId' => 'r'], JSON_THROW_ON_ERROR), $body);

      return new WebhookHttpResponse(200);
    });
    $this->work->expects(self::once())->method('finish')->with($delivery, 0, self::TOKEN)->willReturn(true);
    $this->schedule->expects(self::never())->method('enqueue');
    $this->handler()(new DeliverWebhookCommand(self::DELIVERY_ID));
    self::assertSame(WebhookDeliveryStatus::DELIVERED, $delivery->status());
    self::assertSame(1, $delivery->attempts());
  }

  #[Test]
  public function schedulesHttpFailureWithoutConsumingMessengerTechnicalRetries(): void
  {
    $delivery = $this->delivery(1);
    $this->claimed($delivery);
    $this->work->method('startHttpAttempt')->willReturn(true);
    $this->http->expects(self::once())->method('post')->willReturn(new WebhookHttpResponse(503));
    $this->work->expects(self::once())->method('finish')->with($delivery, 0, self::TOKEN)->willReturn(true);
    $this->schedule->expects(self::once())->method('enqueue')->with(self::DELIVERY_ID, 0, self::isInstanceOf(DateTimeImmutable::class))->willReturn(true);
    $this->work->expects(self::never())->method('technicalFailure');
    $this->handler()(new DeliverWebhookCommand(self::DELIVERY_ID));
    self::assertSame(2, $delivery->attempts());
    self::assertSame(WebhookDeliveryStatus::PENDING, $delivery->status());
    self::assertNotNull($delivery->nextRetryAt());
  }

  #[Test]
  public function stopsAfterFiveHttpAttempts(): void
  {
    $delivery = $this->delivery(4);
    $this->claimed($delivery);
    $this->work->method('startHttpAttempt')->willReturn(true);
    $this->http->expects(self::once())->method('post')->willReturn(new WebhookHttpResponse(503));
    $this->work->expects(self::once())->method('finish')->willReturn(true);
    $this->schedule->expects(self::never())->method('enqueue');
    $this->handler()(new DeliverWebhookCommand(self::DELIVERY_ID));
    self::assertSame(5, $delivery->attempts());
    self::assertSame(WebhookDeliveryStatus::FAILED, $delivery->status());
  }

  #[Test]
  public function preservesADurableWakeWhenTheLeaseIsBusy(): void
  {
    $lease = new DateTimeImmutable('+90 seconds');
    $this->work->method('claim')->willReturn(null);
    $this->repository->method('findById')->willReturn($this->delivery(0, leaseUntil: $lease));
    $this->http->expects(self::never())->method('post');
    $this->work->expects(self::never())->method('startHttpAttempt');
    $this->schedule->expects(self::once())->method('enqueue')->with(self::DELIVERY_ID, 0, self::callback(static fn (DateTimeImmutable $due): bool => $due->getTimestamp() === $lease->getTimestamp()))->willReturn(true);
    $this->handler()(new DeliverWebhookCommand(self::DELIVERY_ID));
  }

  #[Test]
  public function doesNotInterpretAnOldEnvelopeAsTheCurrentGeneration(): void
  {
    $class = DeliverWebhookCommand::class;
    $wire = 'O:' . strlen($class) . ':"' . $class . '":1:{s:10:"deliveryId";s:36:"' . self::DELIVERY_ID . '";}';
    $command = unserialize($wire, ['allowed_classes' => [$class]]);
    self::assertInstanceOf(DeliverWebhookCommand::class, $command);
    self::assertSame(0, $command->generation());
    self::assertNull($command->wakeToken());
    $this->work->expects(self::once())->method('claim')->with(self::DELIVERY_ID, 0, null, self::TOKEN, self::isInstanceOf(DateTimeImmutable::class))->willReturn(null);
    $this->repository->method('findById')->willReturn($this->delivery(generation: 1));
    $this->schedule->expects(self::never())->method('enqueue');
    $this->http->expects(self::never())->method('post');
    $this->handler()($command);
  }

  #[Test]
  public function refusesHttpAfterLosingOwnershipBeforeTheRequest(): void
  {
    $this->schedule->expects(self::never())->method('enqueue');
    $this->claimed($this->delivery());
    $this->work->method('startHttpAttempt')->willReturn(false);
    $this->http->expects(self::never())->method('post');
    $this->work->expects(self::never())->method('finish');
    $this->handler()(new DeliverWebhookCommand(self::DELIVERY_ID));
  }

  #[Test]
  public function doesNotScheduleFromAStaleHttpResponse(): void
  {
    $this->claimed($this->delivery());
    $this->work->method('startHttpAttempt')->willReturn(true);
    $this->http->expects(self::once())->method('post')->willReturn(new WebhookHttpResponse(500));
    $this->work->expects(self::once())->method('finish')->willReturn(false);
    $this->schedule->expects(self::never())->method('enqueue');
    $this->handler()(new DeliverWebhookCommand(self::DELIVERY_ID));
  }

  #[Test]
  public function propagatesTechnicalFailureWithoutSendingOrCountingHttp(): void
  {
    $this->schedule->expects(self::never())->method('enqueue');
    $delivery = $this->delivery();
    $this->work->method('claim')->willReturn($delivery);
    $this->subscriptions->method('findById')->willReturn($this->subscription());
    $this->cipher->method('decrypt')->willThrowException(new RuntimeException('Cipher unavailable'));
    $this->work->expects(self::once())->method('technicalFailure')->with(self::DELIVERY_ID, 0, self::TOKEN, self::isInstanceOf(DateTimeImmutable::class));
    $this->work->expects(self::never())->method('startHttpAttempt');
    $this->http->expects(self::never())->method('post');
    $this->expectException(RuntimeException::class);
    $this->handler()(new DeliverWebhookCommand(self::DELIVERY_ID));
  }

  #[Test]
  public function terminalMissingSubscriptionDoesNotConsumeHttpBudget(): void
  {
    $this->schedule->expects(self::never())->method('enqueue');
    $delivery = $this->delivery();
    $this->work->method('claim')->willReturn($delivery);
    $this->subscriptions->method('findById')->willReturn(null);
    $this->work->expects(self::once())->method('finish')->with($delivery, 0, self::TOKEN)->willReturn(true);
    $this->work->expects(self::never())->method('startHttpAttempt');
    $this->http->expects(self::never())->method('post');
    $this->handler()(new DeliverWebhookCommand(self::DELIVERY_ID));
    self::assertSame(WebhookDeliveryStatus::FAILED, $delivery->status());
    self::assertSame(0, $delivery->attempts());
  }

  #[Test]
  public function invalidStoredDestinationTerminatesBeforeCountingOrSendingHttp(): void
  {
    $delivery = $this->delivery();
    $this->work->method('claim')->willReturn($delivery);
    $this->subscriptions->method('findById')->willReturn($this->subscription('https://127.0.0.1/private'));
    $this->work->expects(self::once())->method('finish')->with($delivery, 0, self::TOKEN)->willReturn(true);
    $this->work->expects(self::never())->method('startHttpAttempt');
    $this->http->expects(self::never())->method('post');
    $this->schedule->expects(self::never())->method('enqueue');
    $this->handler()(new DeliverWebhookCommand(self::DELIVERY_ID));
    self::assertSame(WebhookDeliveryStatus::FAILED, $delivery->status());
    self::assertSame(0, $delivery->attempts());
  }

  private function claimed(WebhookDelivery $delivery): void
  {
    $this->work->method('claim')->willReturn($delivery);
    $this->subscriptions->method('findById')->willReturn($this->subscription());
    $this->cipher->method('decrypt')->willReturn('secret');
  }

  private function handler(): DeliverWebhookHandler
  {
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::TOKEN);
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());

    return new DeliverWebhookHandler($this->repository, $this->subscriptions, $this->cipher, $this->http, $this->createStub(LoggerPort::class), $this->work, $this->schedule, $ids, $transactions, new WebhookUrlPolicy());
  }

  private function delivery(int $attempts = 0, int $generation = 0, ?DateTimeImmutable $leaseUntil = null): WebhookDelivery
  {
    $now = new DateTimeImmutable();

    return WebhookDelivery::reconstitute(
      WebhookDeliveryId::fromString(self::DELIVERY_ID),
      WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org',
      new RestoredWebhookDeliveryMetadata('intervention.published', 'event', ['resourceId' => 'r'], $now, $now),
      new RestoredWebhookDeliveryAttempt(WebhookDeliveryStatus::PENDING, $attempts, null, null, null, null, $generation, $leaseUntil),
    );
  }

  private function subscription(string $url = 'https://example.com/hook'): WebhookSubscription
  {
    return WebhookSubscription::create(WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID), 'org', $url, 'cipher', ['intervention.published']);
  }
}
