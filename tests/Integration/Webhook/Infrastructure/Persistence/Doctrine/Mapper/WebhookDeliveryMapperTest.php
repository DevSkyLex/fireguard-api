<?php

declare(strict_types=1);

namespace Tests\Integration\Webhook\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webhook\Domain\Model\Delivery\WebhookDelivery;
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookDeliveryStatus, WebhookSubscriptionId};
use Webhook\Infrastructure\Persistence\Doctrine\Mapper\WebhookDeliveryMapper;
use Webhook\Infrastructure\Persistence\Doctrine\Record\{WebhookDeliveryRecord, WebhookSubscriptionRecord};

/**
 * Test WebhookDeliveryMapper.
 *
 * `WebhookDeliveryMapper` is a stateless, all-static mapper used statically by
 * `WebhookDeliveryRepository` (never injected), so it is exercised here through
 * real-database round-trips rather than a container-fetched service: a
 * reconstituted aggregate is mapped onto a record, persisted with the real
 * `main` entity manager, re-fetched, and mapped back — validating the JSON
 * `payload` column and every nullable timestamp/status column round-trip end to
 * end, in both their populated and null variants.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookDeliveryMapper::class)]
final class WebhookDeliveryMapperTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'b7c3e1a0-0000-4000-8000-0000000009a0';

  private const string SUBSCRIPTION_ID = 'b7c3e1a0-0000-4000-8000-0000000009b0';

  private const string DELIVERED_DELIVERY_ID = 'b7c3e1a0-0000-4000-8000-0000000009c0';

  private const string PENDING_DELIVERY_ID = 'b7c3e1a0-0000-4000-8000-0000000009c1';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->createOrganization();
    $this->createSubscription();
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testToRecordThenToDomainRoundTripsEveryFieldForADeliveredDelivery(): void
  {
    $createdAt = new DateTimeImmutable('2026-05-01T08:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-05-01T08:05:00+00:00');
    $deliveredAt = new DateTimeImmutable('2026-05-01T08:05:00+00:00');
    $payload = [
      'id' => self::DELIVERED_DELIVERY_ID,
      'type' => 'intervention.published',
      'data' => ['interventionId' => 'iv-1', 'nested' => ['a', 'b']],
    ];

    $delivery = WebhookDelivery::reconstitute(
      id: WebhookDeliveryId::fromString(self::DELIVERED_DELIVERY_ID),
      subscriptionId: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: self::ORGANIZATION_ID,
      eventType: 'intervention.published',
      eventId: 'event-delivered-1',
      payload: $payload,
      status: WebhookDeliveryStatus::DELIVERED,
      attempts: 2,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      httpStatus: 200,
      lastError: null,
      nextRetryAt: null,
      deliveredAt: $deliveredAt,
    );

    // toRecord: assert the record was populated directly from the aggregate.
    $record = new WebhookDeliveryRecord();
    WebhookDeliveryMapper::toRecord($delivery, $record);

    self::assertSame(self::DELIVERED_DELIVERY_ID, $record->id);
    self::assertSame(self::SUBSCRIPTION_ID, $record->subscriptionId);
    self::assertSame(self::ORGANIZATION_ID, $record->organizationId);
    self::assertSame('intervention.published', $record->eventType);
    self::assertSame('event-delivered-1', $record->eventId);
    self::assertSame($payload, $record->payload);
    self::assertSame('delivered', $record->status);
    self::assertSame(2, $record->attempts);
    self::assertSame(200, $record->httpStatus);
    self::assertNull($record->lastError);
    self::assertNull($record->nextRetryAt);
    self::assertSame($deliveredAt, $record->deliveredAt);
    self::assertSame($createdAt, $record->createdAt);
    self::assertSame($updatedAt, $record->updatedAt);

    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    // toDomain: re-hydrate from the real database and assert every field maps
    // back, including the JSON payload and the populated nullable columns.
    $fetched = $this->entityManager->find(WebhookDeliveryRecord::class, self::DELIVERED_DELIVERY_ID);
    self::assertInstanceOf(WebhookDeliveryRecord::class, $fetched);

    $mapped = WebhookDeliveryMapper::toDomain($fetched);

    self::assertSame(self::DELIVERED_DELIVERY_ID, (string) $mapped->id());
    self::assertSame(self::SUBSCRIPTION_ID, (string) $mapped->subscriptionId());
    self::assertSame(self::ORGANIZATION_ID, $mapped->organizationId());
    self::assertSame('intervention.published', $mapped->eventType());
    self::assertSame('event-delivered-1', $mapped->eventId());
    self::assertSame($payload, $mapped->payload());
    self::assertSame(WebhookDeliveryStatus::DELIVERED, $mapped->status());
    self::assertSame(2, $mapped->attempts());
    self::assertSame(200, $mapped->httpStatus());
    self::assertNull($mapped->lastError());
    self::assertNull($mapped->nextRetryAt());
    self::assertInstanceOf(DateTimeImmutable::class, $mapped->deliveredAt());
    self::assertSame($deliveredAt->format('Y-m-d H:i:s'), $mapped->deliveredAt()->format('Y-m-d H:i:s'));
    self::assertSame($createdAt->format('Y-m-d H:i:s'), $mapped->createdAt()->format('Y-m-d H:i:s'));
    self::assertSame($updatedAt->format('Y-m-d H:i:s'), $mapped->updatedAt()->format('Y-m-d H:i:s'));
  }

  #[Test]
  public function testToRecordThenToDomainRoundTripsAPendingDeliveryWithAnErrorAndRetryButNoDeliveredAt(): void
  {
    $createdAt = new DateTimeImmutable('2026-06-02T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-06-02T09:01:00+00:00');
    $nextRetryAt = new DateTimeImmutable('2026-06-02T09:06:00+00:00');
    $payload = ['id' => self::PENDING_DELIVERY_ID, 'type' => 'inspection.submitted'];

    $delivery = WebhookDelivery::reconstitute(
      id: WebhookDeliveryId::fromString(self::PENDING_DELIVERY_ID),
      subscriptionId: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: self::ORGANIZATION_ID,
      eventType: 'inspection.submitted',
      eventId: 'event-pending-1',
      payload: $payload,
      status: WebhookDeliveryStatus::PENDING,
      attempts: 1,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      httpStatus: 503,
      lastError: 'Service Unavailable',
      nextRetryAt: $nextRetryAt,
      deliveredAt: null,
    );

    // toRecord: the retry-pending shape sets httpStatus/lastError/nextRetryAt
    // while leaving deliveredAt null.
    $record = new WebhookDeliveryRecord();
    WebhookDeliveryMapper::toRecord($delivery, $record);

    self::assertSame('pending', $record->status);
    self::assertSame(1, $record->attempts);
    self::assertSame(503, $record->httpStatus);
    self::assertSame('Service Unavailable', $record->lastError);
    self::assertSame($nextRetryAt, $record->nextRetryAt);
    self::assertNull($record->deliveredAt);

    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $fetched = $this->entityManager->find(WebhookDeliveryRecord::class, self::PENDING_DELIVERY_ID);
    self::assertInstanceOf(WebhookDeliveryRecord::class, $fetched);

    $mapped = WebhookDeliveryMapper::toDomain($fetched);

    self::assertSame(WebhookDeliveryStatus::PENDING, $mapped->status());
    self::assertSame(1, $mapped->attempts());
    self::assertSame(503, $mapped->httpStatus());
    self::assertSame('Service Unavailable', $mapped->lastError());
    self::assertInstanceOf(DateTimeImmutable::class, $mapped->nextRetryAt());
    self::assertSame($nextRetryAt->format('Y-m-d H:i:s'), $mapped->nextRetryAt()->format('Y-m-d H:i:s'));
    self::assertNull($mapped->deliveredAt());
    self::assertSame($payload, $mapped->payload());
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Webhook Delivery Mapper Test';
    $organization->slug = 'webhook-delivery-mapper-test';
    $organization->ownerUserId = 'b7c3e1a0-0000-4000-8000-000000009000';
    $organization->createdByUserId = 'b7c3e1a0-0000-4000-8000-000000009000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createSubscription(): void
  {
    $record = new WebhookSubscriptionRecord();
    $record->id = self::SUBSCRIPTION_ID;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->url = 'https://hooks.example.test/mapper';
    $record->secretCiphertext = 'ciphertext';
    $record->eventTypes = ['intervention.published', 'inspection.submitted'];
    $record->isActive = true;
    $record->description = '';
    $record->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM webhook_deliveries WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM webhook_subscriptions WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
