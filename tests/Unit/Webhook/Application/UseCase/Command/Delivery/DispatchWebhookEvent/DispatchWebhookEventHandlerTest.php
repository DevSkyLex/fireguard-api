<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\UseCase\Command\Delivery\DispatchWebhookEvent;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{TransactionManagerPort, UuidGeneratorPort};
use Webhook\Application\Port\Outbound\Internal\{WebhookDeliveryRepositoryPort, WebhookSubscriptionRepositoryPort};
use Webhook\Application\Port\Outbound\WebhookDeliverySchedulePort;
use Webhook\Application\UseCase\Command\Delivery\DispatchWebhookEvent\{DispatchWebhookEventCommand, DispatchWebhookEventHandler};
use Webhook\Domain\Model\Subscription\{RestoredWebhookSubscriptionMetadata, WebhookSubscription};
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

/**
 * Test DispatchWebhookEventHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DispatchWebhookEventHandler::class)]
final class DispatchWebhookEventHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  #[Test]
  public function itReservesAndEnqueuesOneDeliveryPerMatchingActiveSubscription(): void
  {
    $subscriptionA = $this->subscription('018f0b68-6758-7a12-8a1d-3f0d97f64a10');
    $subscriptionB = $this->subscription('018f0b68-6758-7a12-8a1d-3f0d97f64a11');

    $subscriptionRepository = $this->createMock(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->expects(self::once())
      ->method('findActiveByOrganizationAndEventType')
      ->with(self::ORGANIZATION_ID, 'intervention.published')
      ->willReturn([$subscriptionA, $subscriptionB]);

    $deliveryRepository = $this->createMock(WebhookDeliveryRepositoryPort::class);
    $deliveryRepository->expects(self::exactly(2))
      ->method('reserve')
      ->willReturn(true);

    $queue = $this->createMock(WebhookDeliverySchedulePort::class);
    $queue->expects(self::exactly(2))->method('enqueue');

    $handler = new DispatchWebhookEventHandler($subscriptionRepository, $deliveryRepository, $queue, $this->uuidFactory(), $this->transactions());

    $handler->__invoke(new DispatchWebhookEventCommand(
      organizationId: self::ORGANIZATION_ID,
      eventType: 'intervention.published',
      eventId: 'event-1',
      data: ['interventionId' => 'i-1'],
      occurredAt: new DateTimeImmutable(),
    ));
  }

  #[Test]
  public function itDoesNotEnqueueWhenTheReservationIsADuplicate(): void
  {
    $subscription = $this->subscription('018f0b68-6758-7a12-8a1d-3f0d97f64a10');

    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->method('findActiveByOrganizationAndEventType')->willReturn([$subscription]);

    $deliveryRepository = $this->createStub(WebhookDeliveryRepositoryPort::class);
    $deliveryRepository->method('reserve')->willReturn(false);

    $queue = $this->createMock(WebhookDeliverySchedulePort::class);
    $queue->expects(self::never())->method('enqueue');

    $handler = new DispatchWebhookEventHandler($subscriptionRepository, $deliveryRepository, $queue, $this->uuidFactory(), $this->transactions());

    $handler->__invoke(new DispatchWebhookEventCommand(
      organizationId: self::ORGANIZATION_ID,
      eventType: 'intervention.published',
      eventId: 'event-1',
      data: [],
      occurredAt: new DateTimeImmutable(),
    ));
  }

  #[Test]
  public function itDoesNothingWhenNoSubscriptionMatches(): void
  {
    $subscriptionRepository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $subscriptionRepository->method('findActiveByOrganizationAndEventType')->willReturn([]);

    $deliveryRepository = $this->createMock(WebhookDeliveryRepositoryPort::class);
    $deliveryRepository->expects(self::never())->method('reserve');

    $queue = $this->createMock(WebhookDeliverySchedulePort::class);
    $queue->expects(self::never())->method('enqueue');

    $handler = new DispatchWebhookEventHandler($subscriptionRepository, $deliveryRepository, $queue, $this->uuidFactory(), $this->transactions());

    $handler->__invoke(new DispatchWebhookEventCommand(
      organizationId: self::ORGANIZATION_ID,
      eventType: 'facility.archived',
      eventId: 'event-2',
      data: [],
      occurredAt: new DateTimeImmutable(),
    ));
  }

  /**
   * Method subscription.
   *
   * @param string $id the subscription identifier
   *
   * @return WebhookSubscription an active subscription fixture
   */
  private function subscription(string $id): WebhookSubscription
  {
    return WebhookSubscription::reconstitute(
      id: WebhookSubscriptionId::fromString($id),
      organizationId: self::ORGANIZATION_ID,
      url: 'https://example.com/hook',
      secretCiphertext: 'ciphertext',
      eventTypes: ['intervention.published'],
      isActive: true,
      metadata: new RestoredWebhookSubscriptionMetadata('', new DateTimeImmutable(), new DateTimeImmutable()),
    );
  }

  /**
   * Method uuidFactory.
   *
   * @return UuidGeneratorPort a uuid factory generating deterministic-enough raw ids
   */
  private function uuidFactory(): UuidGeneratorPort
  {
    $generator = $this->createStub(UuidGeneratorPort::class);
    $generator->method('generate')->willReturn('018f0b68-6758-7a12-8a1d-3f0d97f64aff');

    return $generator;
  }

  /**
   * Executes the owning transaction callback without a database in handler unit tests.
   */
  private function transactions(): TransactionManagerPort
  {
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());

    return $transactions;
  }
}
