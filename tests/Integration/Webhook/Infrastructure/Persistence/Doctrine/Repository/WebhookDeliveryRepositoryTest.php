<?php

declare(strict_types=1);

namespace Tests\Integration\Webhook\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webhook\Domain\Model\Delivery\WebhookDelivery;
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookDeliveryStatus, WebhookSubscriptionId};
use Webhook\Infrastructure\Persistence\Doctrine\Record\WebhookSubscriptionRecord;
use Webhook\Infrastructure\Persistence\Doctrine\Repository\WebhookDeliveryRepository;

use function array_map;

/**
 * Test WebhookDeliveryRepository.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookDeliveryRepository::class)]
final class WebhookDeliveryRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'a1b2c3d4-0000-4000-8000-000000000001';

  private const string SUBSCRIPTION_ID = 'a1b2c3d4-0000-4000-8000-000000000010';

  private const string OTHER_SUBSCRIPTION_ID = 'a1b2c3d4-0000-4000-8000-000000000011';

  private EntityManagerInterface $entityManager;

  private WebhookDeliveryRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var WebhookDeliveryRepository $repository */
    $repository = static::getContainer()->get(WebhookDeliveryRepository::class);
    $this->repository = $repository;

    $this->createOrganization();
    $this->createSubscription(self::SUBSCRIPTION_ID, 'https://hooks.example.test/a');
    $this->createSubscription(self::OTHER_SUBSCRIPTION_ID, 'https://hooks.example.test/b');
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
  public function testReserveInsertsPendingRowAndIsIdempotentOnDuplicateEvent(): void
  {
    $firstId = WebhookDeliveryId::fromString('a1b2c3d4-0000-4000-8000-000000000100');
    $subscriptionId = WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID);
    $payload = ['id' => (string) $firstId, 'type' => 'intervention.published', 'data' => ['interventionId' => 'x']];

    $inserted = $this->repository->reserve(
      $firstId,
      $subscriptionId,
      self::ORGANIZATION_ID,
      'intervention.published',
      'event-1',
      $payload,
    );
    self::assertTrue($inserted);

    $this->entityManager->clear();
    $reserved = $this->repository->findById($firstId);
    self::assertInstanceOf(WebhookDelivery::class, $reserved);
    self::assertSame(WebhookDeliveryStatus::PENDING, $reserved->status());
    self::assertSame(0, $reserved->attempts());
    self::assertSame(self::ORGANIZATION_ID, $reserved->organizationId());
    self::assertSame('event-1', $reserved->eventId());
    self::assertSame($payload, $reserved->payload());

    // Same (subscription_id, event_id) pair — ON CONFLICT DO NOTHING makes the
    // duplicate a no-op returning zero affected rows.
    $duplicate = $this->repository->reserve(
      WebhookDeliveryId::fromString('a1b2c3d4-0000-4000-8000-000000000101'),
      $subscriptionId,
      self::ORGANIZATION_ID,
      'intervention.published',
      'event-1',
      $payload,
    );
    self::assertFalse($duplicate);

    // Distinct event id for the same subscription — a fresh reservation.
    $distinct = $this->repository->reserve(
      WebhookDeliveryId::fromString('a1b2c3d4-0000-4000-8000-000000000102'),
      $subscriptionId,
      self::ORGANIZATION_ID,
      'intervention.published',
      'event-2',
      $payload,
    );
    self::assertTrue($distinct);
  }

  #[Test]
  public function testSaveInsertsThenUpdatesTheSameDelivery(): void
  {
    $id = WebhookDeliveryId::fromString('a1b2c3d4-0000-4000-8000-000000000200');
    $delivery = WebhookDelivery::create(
      $id,
      WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      self::ORGANIZATION_ID,
      'inspection.submitted',
      'event-save-1',
      ['id' => (string) $id, 'type' => 'inspection.submitted'],
    );

    // Insert branch: no record exists for this id yet.
    $this->repository->save($delivery);
    $this->entityManager->clear();

    $stored = $this->repository->findById($id);
    self::assertInstanceOf(WebhookDelivery::class, $stored);
    self::assertSame(WebhookDeliveryStatus::PENDING, $stored->status());
    self::assertSame(0, $stored->attempts());
    self::assertNull($stored->deliveredAt());

    // Update branch: the mutated aggregate re-hydrates the existing record.
    $stored->markDelivered(200, new DateTimeImmutable('2026-07-24T12:00:00+00:00'));
    $this->repository->save($stored);
    $this->entityManager->clear();

    $updated = $this->repository->findById($id);
    self::assertInstanceOf(WebhookDelivery::class, $updated);
    self::assertSame(WebhookDeliveryStatus::DELIVERED, $updated->status());
    self::assertSame(1, $updated->attempts());
    self::assertSame(200, $updated->httpStatus());
    self::assertInstanceOf(DateTimeImmutable::class, $updated->deliveredAt());
  }

  #[Test]
  public function testFindByIdReturnsNullWhenAbsent(): void
  {
    self::assertNull(
      $this->repository->findById(WebhookDeliveryId::fromString('a1b2c3d4-0000-4000-8000-0000000009ff')),
    );
  }

  #[Test]
  public function testListBySubscriptionOrdersByCreatedAtDescendingAndScopesToSubscription(): void
  {
    $oldest = 'a1b2c3d4-0000-4000-8000-000000000300';
    $middle = 'a1b2c3d4-0000-4000-8000-000000000301';
    $newest = 'a1b2c3d4-0000-4000-8000-000000000302';

    $this->saveDelivery($oldest, self::SUBSCRIPTION_ID, 'e-old', WebhookDeliveryStatus::PENDING, '2026-01-01T00:00:00+00:00');
    $this->saveDelivery($middle, self::SUBSCRIPTION_ID, 'e-mid', WebhookDeliveryStatus::PENDING, '2026-01-02T00:00:00+00:00');
    $this->saveDelivery($newest, self::SUBSCRIPTION_ID, 'e-new', WebhookDeliveryStatus::PENDING, '2026-01-03T00:00:00+00:00');
    // Belongs to a different subscription: must be excluded from the scoped log.
    $this->saveDelivery('a1b2c3d4-0000-4000-8000-000000000399', self::OTHER_SUBSCRIPTION_ID, 'e-other', WebhookDeliveryStatus::PENDING, '2026-01-04T00:00:00+00:00');
    $this->entityManager->clear();

    $deliveries = $this->repository->listBySubscription(
      WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      null,
      10,
      0,
    );

    self::assertCount(3, $deliveries);
    $ids = array_map(static fn (WebhookDelivery $delivery): string => (string) $delivery->id(), $deliveries);
    self::assertSame([$newest, $middle, $oldest], $ids);
  }

  #[Test]
  public function testListBySubscriptionFiltersByStatusAndPaginates(): void
  {
    $this->saveDelivery('a1b2c3d4-0000-4000-8000-000000000400', self::SUBSCRIPTION_ID, 'f-1', WebhookDeliveryStatus::PENDING, '2026-02-01T00:00:00+00:00');
    $this->saveDelivery('a1b2c3d4-0000-4000-8000-000000000401', self::SUBSCRIPTION_ID, 'f-2', WebhookDeliveryStatus::DELIVERED, '2026-02-02T00:00:00+00:00');
    $this->saveDelivery('a1b2c3d4-0000-4000-8000-000000000402', self::SUBSCRIPTION_ID, 'f-3', WebhookDeliveryStatus::PENDING, '2026-02-03T00:00:00+00:00');
    $this->entityManager->clear();

    $subscriptionId = WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID);

    $delivered = $this->repository->listBySubscription($subscriptionId, WebhookDeliveryStatus::DELIVERED->value, 10, 0);
    self::assertCount(1, $delivered);
    self::assertSame(WebhookDeliveryStatus::DELIVERED, $delivered[0]->status());
    self::assertSame('a1b2c3d4-0000-4000-8000-000000000401', (string) $delivered[0]->id());

    // Pagination over the pending subset (newest first): limit 1, offset 1.
    $secondPending = $this->repository->listBySubscription($subscriptionId, WebhookDeliveryStatus::PENDING->value, 1, 1);
    self::assertCount(1, $secondPending);
    self::assertSame('a1b2c3d4-0000-4000-8000-000000000400', (string) $secondPending[0]->id());
  }

  #[Test]
  public function testCountBySubscriptionWithAndWithoutStatusFilter(): void
  {
    $this->saveDelivery('a1b2c3d4-0000-4000-8000-000000000500', self::SUBSCRIPTION_ID, 'c-1', WebhookDeliveryStatus::PENDING, '2026-03-01T00:00:00+00:00');
    $this->saveDelivery('a1b2c3d4-0000-4000-8000-000000000501', self::SUBSCRIPTION_ID, 'c-2', WebhookDeliveryStatus::DELIVERED, '2026-03-02T00:00:00+00:00');
    $this->saveDelivery('a1b2c3d4-0000-4000-8000-000000000502', self::SUBSCRIPTION_ID, 'c-3', WebhookDeliveryStatus::PENDING, '2026-03-03T00:00:00+00:00');
    // Another subscription's row must not leak into the count.
    $this->saveDelivery('a1b2c3d4-0000-4000-8000-000000000599', self::OTHER_SUBSCRIPTION_ID, 'c-other', WebhookDeliveryStatus::PENDING, '2026-03-04T00:00:00+00:00');
    $this->entityManager->clear();

    $subscriptionId = WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID);

    self::assertSame(3, $this->repository->countBySubscription($subscriptionId, null));
    self::assertSame(2, $this->repository->countBySubscription($subscriptionId, WebhookDeliveryStatus::PENDING->value));
    self::assertSame(1, $this->repository->countBySubscription($subscriptionId, WebhookDeliveryStatus::DELIVERED->value));
  }

  /**
   * Builds a reconstituted delivery with a controlled createdAt and status,
   * then persists it through the repository under test.
   *
   * @param string $id the delivery identifier
   * @param string $subscriptionId the owning subscription identifier
   * @param string $eventId the source event identifier (dedupe key)
   * @param WebhookDeliveryStatus $status the lifecycle status
   * @param string $createdAt the ISO-8601 creation timestamp
   */
  private function saveDelivery(string $id, string $subscriptionId, string $eventId, WebhookDeliveryStatus $status, string $createdAt): void
  {
    $timestamp = new DateTimeImmutable($createdAt);
    $delivery = WebhookDelivery::reconstitute(
      id: WebhookDeliveryId::fromString($id),
      subscriptionId: WebhookSubscriptionId::fromString($subscriptionId),
      organizationId: self::ORGANIZATION_ID,
      eventType: 'intervention.published',
      eventId: $eventId,
      payload: ['id' => $id],
      status: $status,
      attempts: WebhookDeliveryStatus::DELIVERED === $status ? 1 : 0,
      createdAt: $timestamp,
      updatedAt: $timestamp,
      httpStatus: WebhookDeliveryStatus::DELIVERED === $status ? 200 : null,
      lastError: null,
      nextRetryAt: null,
      deliveredAt: WebhookDeliveryStatus::DELIVERED === $status ? $timestamp : null,
    );

    $this->repository->save($delivery);
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Webhook Delivery Repository Test';
    $organization->slug = 'webhook-delivery-repository-test';
    $organization->ownerUserId = 'a1b2c3d4-0000-4000-8000-000000009000';
    $organization->createdByUserId = 'a1b2c3d4-0000-4000-8000-000000009000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createSubscription(string $id, string $url): void
  {
    $record = new WebhookSubscriptionRecord();
    $record->id = $id;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->url = $url;
    $record->secretCiphertext = 'ciphertext';
    $record->eventTypes = ['intervention.published'];
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
