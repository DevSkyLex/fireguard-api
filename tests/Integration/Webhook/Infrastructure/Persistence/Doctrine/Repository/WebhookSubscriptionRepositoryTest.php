<?php

declare(strict_types=1);

namespace Tests\Integration\Webhook\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;
use Webhook\Infrastructure\Persistence\Doctrine\Repository\WebhookSubscriptionRepository;

use function array_map;

/**
 * Test WebhookSubscriptionRepository.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookSubscriptionRepository::class)]
final class WebhookSubscriptionRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'b1c2d3e4-0000-4000-8000-000000000001';

  private const string OTHER_ORGANIZATION_ID = 'b1c2d3e4-0000-4000-8000-000000000002';

  private EntityManagerInterface $entityManager;

  private WebhookSubscriptionRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var WebhookSubscriptionRepository $repository */
    $repository = static::getContainer()->get(WebhookSubscriptionRepository::class);
    $this->repository = $repository;

    $this->createOrganization(self::ORGANIZATION_ID, 'webhook-subscription-repository-test');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'webhook-subscription-repository-other');
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
  public function testSaveInsertsThenUpdatesTheSameSubscription(): void
  {
    $id = WebhookSubscriptionId::fromString('b1c2d3e4-0000-4000-8000-000000000100');
    $subscription = WebhookSubscription::create(
      $id,
      self::ORGANIZATION_ID,
      'https://hooks.example.test/insert',
      'cipher-insert',
      ['intervention.published'],
      'Initial description',
    );

    // Insert branch: no record exists for this id yet.
    $this->repository->save($subscription);
    $this->entityManager->clear();

    $stored = $this->repository->findById($id);
    self::assertInstanceOf(WebhookSubscription::class, $stored);
    self::assertSame(self::ORGANIZATION_ID, $stored->organizationId());
    self::assertSame('https://hooks.example.test/insert', $stored->url());
    self::assertSame('cipher-insert', $stored->secretCiphertext());
    self::assertSame(['intervention.published'], $stored->eventTypes());
    self::assertTrue($stored->isActive());
    self::assertSame('Initial description', $stored->description());

    // Update branch: the mutated aggregate re-hydrates the existing record.
    $subscription->update(
      'https://hooks.example.test/updated',
      ['intervention.published', 'inspection.submitted'],
      false,
      'Updated description',
    );
    $subscription->rotateSecret('cipher-rotated');
    $this->repository->save($subscription);
    $this->entityManager->clear();

    $updated = $this->repository->findById($id);
    self::assertInstanceOf(WebhookSubscription::class, $updated);
    self::assertSame('https://hooks.example.test/updated', $updated->url());
    self::assertSame(['intervention.published', 'inspection.submitted'], $updated->eventTypes());
    self::assertFalse($updated->isActive());
    self::assertSame('Updated description', $updated->description());
    self::assertSame('cipher-rotated', $updated->secretCiphertext());
  }

  #[Test]
  public function testFindByIdReturnsNullWhenAbsent(): void
  {
    self::assertNull(
      $this->repository->findById(WebhookSubscriptionId::fromString('b1c2d3e4-0000-4000-8000-0000000009ff')),
    );
  }

  #[Test]
  public function testRemoveDeletesExistingRecordAndIsNoopWhenAbsent(): void
  {
    $id = WebhookSubscriptionId::fromString('b1c2d3e4-0000-4000-8000-000000000200');
    $this->saveSubscription(
      (string) $id,
      self::ORGANIZATION_ID,
      'https://hooks.example.test/remove',
      ['intervention.published'],
      true,
      '2026-05-01T00:00:00+00:00',
    );
    $this->entityManager->clear();

    $persisted = $this->repository->findById($id);
    self::assertInstanceOf(WebhookSubscription::class, $persisted);

    $this->repository->remove($persisted);
    $this->entityManager->clear();
    self::assertNull($this->repository->findById($id));

    // Absent branch: removing an aggregate with no matching record is a no-op.
    $unsaved = WebhookSubscription::create(
      WebhookSubscriptionId::fromString('b1c2d3e4-0000-4000-8000-0000000002ff'),
      self::ORGANIZATION_ID,
      'https://hooks.example.test/ghost',
      'cipher-ghost',
      ['intervention.published'],
    );
    $this->repository->remove($unsaved);
    self::assertNull(
      $this->repository->findById(WebhookSubscriptionId::fromString('b1c2d3e4-0000-4000-8000-0000000002ff')),
    );
  }

  #[Test]
  public function testListByOrganizationOrdersByCreatedAtDescendingAndScopesToOrganization(): void
  {
    $oldest = 'b1c2d3e4-0000-4000-8000-000000000300';
    $middle = 'b1c2d3e4-0000-4000-8000-000000000301';
    $newest = 'b1c2d3e4-0000-4000-8000-000000000302';

    $this->saveSubscription($oldest, self::ORGANIZATION_ID, 'https://hooks.example.test/old', ['intervention.published'], true, '2026-01-01T00:00:00+00:00');
    $this->saveSubscription($middle, self::ORGANIZATION_ID, 'https://hooks.example.test/mid', ['intervention.published'], true, '2026-01-02T00:00:00+00:00');
    $this->saveSubscription($newest, self::ORGANIZATION_ID, 'https://hooks.example.test/new', ['intervention.published'], true, '2026-01-03T00:00:00+00:00');
    // Belongs to another organization: must be excluded from the scoped list.
    $this->saveSubscription('b1c2d3e4-0000-4000-8000-000000000399', self::OTHER_ORGANIZATION_ID, 'https://hooks.example.test/other', ['intervention.published'], true, '2026-01-04T00:00:00+00:00');
    $this->entityManager->clear();

    $subscriptions = $this->repository->listByOrganization(self::ORGANIZATION_ID, 10, 0);

    self::assertCount(3, $subscriptions);
    self::assertInstanceOf(WebhookSubscription::class, $subscriptions[0]);
    $ids = array_map(static fn (WebhookSubscription $subscription): string => (string) $subscription->id(), $subscriptions);
    self::assertSame([$newest, $middle, $oldest], $ids);
  }

  #[Test]
  public function testListByOrganizationBreaksCreatedAtTiesByIdDescending(): void
  {
    $lower = 'b1c2d3e4-0000-4000-8000-0000000003a0';
    $higher = 'b1c2d3e4-0000-4000-8000-0000000003a1';

    $this->saveSubscription($lower, self::ORGANIZATION_ID, 'https://hooks.example.test/tie-a', ['intervention.published'], true, '2026-04-01T00:00:00+00:00');
    $this->saveSubscription($higher, self::ORGANIZATION_ID, 'https://hooks.example.test/tie-b', ['intervention.published'], true, '2026-04-01T00:00:00+00:00');
    $this->entityManager->clear();

    $subscriptions = $this->repository->listByOrganization(self::ORGANIZATION_ID, 10, 0);

    $ids = array_map(static fn (WebhookSubscription $subscription): string => (string) $subscription->id(), $subscriptions);
    self::assertSame([$higher, $lower], $ids);
  }

  #[Test]
  public function testListByOrganizationPaginates(): void
  {
    $oldest = 'b1c2d3e4-0000-4000-8000-000000000400';
    $middle = 'b1c2d3e4-0000-4000-8000-000000000401';
    $newest = 'b1c2d3e4-0000-4000-8000-000000000402';

    $this->saveSubscription($oldest, self::ORGANIZATION_ID, 'https://hooks.example.test/p-old', ['intervention.published'], true, '2026-02-01T00:00:00+00:00');
    $this->saveSubscription($middle, self::ORGANIZATION_ID, 'https://hooks.example.test/p-mid', ['intervention.published'], true, '2026-02-02T00:00:00+00:00');
    $this->saveSubscription($newest, self::ORGANIZATION_ID, 'https://hooks.example.test/p-new', ['intervention.published'], true, '2026-02-03T00:00:00+00:00');
    $this->entityManager->clear();

    $firstPage = $this->repository->listByOrganization(self::ORGANIZATION_ID, 2, 0);
    self::assertCount(2, $firstPage);
    self::assertSame($newest, (string) $firstPage[0]->id());
    self::assertSame($middle, (string) $firstPage[1]->id());

    $secondPage = $this->repository->listByOrganization(self::ORGANIZATION_ID, 2, 2);
    self::assertCount(1, $secondPage);
    self::assertSame($oldest, (string) $secondPage[0]->id());
  }

  #[Test]
  public function testCountByOrganizationScopesToOrganization(): void
  {
    $this->saveSubscription('b1c2d3e4-0000-4000-8000-000000000500', self::ORGANIZATION_ID, 'https://hooks.example.test/c-1', ['intervention.published'], true, '2026-03-01T00:00:00+00:00');
    $this->saveSubscription('b1c2d3e4-0000-4000-8000-000000000501', self::ORGANIZATION_ID, 'https://hooks.example.test/c-2', ['intervention.published'], true, '2026-03-02T00:00:00+00:00');
    $this->saveSubscription('b1c2d3e4-0000-4000-8000-000000000502', self::ORGANIZATION_ID, 'https://hooks.example.test/c-3', ['intervention.published'], true, '2026-03-03T00:00:00+00:00');
    // Another organization's row must not leak into the count.
    $this->saveSubscription('b1c2d3e4-0000-4000-8000-000000000599', self::OTHER_ORGANIZATION_ID, 'https://hooks.example.test/c-other', ['intervention.published'], true, '2026-03-04T00:00:00+00:00');
    $this->entityManager->clear();

    self::assertSame(3, $this->repository->countByOrganization(self::ORGANIZATION_ID));
    self::assertSame(1, $this->repository->countByOrganization(self::OTHER_ORGANIZATION_ID));
  }

  #[Test]
  public function testCountActiveByOrganizationExcludesInactiveSubscriptions(): void
  {
    $this->saveSubscription('b1c2d3e4-0000-4000-8000-000000000600', self::ORGANIZATION_ID, 'https://hooks.example.test/a-1', ['intervention.published'], true, '2026-06-01T00:00:00+00:00');
    $this->saveSubscription('b1c2d3e4-0000-4000-8000-000000000601', self::ORGANIZATION_ID, 'https://hooks.example.test/a-2', ['intervention.published'], true, '2026-06-02T00:00:00+00:00');
    $this->saveSubscription('b1c2d3e4-0000-4000-8000-000000000602', self::ORGANIZATION_ID, 'https://hooks.example.test/a-3', ['intervention.published'], false, '2026-06-03T00:00:00+00:00');
    $this->entityManager->clear();

    self::assertSame(3, $this->repository->countByOrganization(self::ORGANIZATION_ID));
    self::assertSame(2, $this->repository->countActiveByOrganization(self::ORGANIZATION_ID));
  }

  #[Test]
  public function testFindActiveByOrganizationAndEventTypeMatchesActiveSubscribersOnly(): void
  {
    $matching = 'b1c2d3e4-0000-4000-8000-000000000700';
    $wrongEvent = 'b1c2d3e4-0000-4000-8000-000000000701';
    $inactive = 'b1c2d3e4-0000-4000-8000-000000000702';
    $otherOrg = 'b1c2d3e4-0000-4000-8000-000000000703';

    // Active and subscribed to the event: the only expected match.
    $this->saveSubscription($matching, self::ORGANIZATION_ID, 'https://hooks.example.test/e-match', ['intervention.published', 'inspection.submitted'], true, '2026-07-01T00:00:00+00:00');
    // Active but not subscribed to the queried event type: filtered out in PHP.
    $this->saveSubscription($wrongEvent, self::ORGANIZATION_ID, 'https://hooks.example.test/e-wrong', ['inspection.submitted'], true, '2026-07-02T00:00:00+00:00');
    // Subscribed to the event but inactive: excluded by the isActive filter.
    $this->saveSubscription($inactive, self::ORGANIZATION_ID, 'https://hooks.example.test/e-inactive', ['intervention.published'], false, '2026-07-03T00:00:00+00:00');
    // Active and subscribed but in another organization: excluded by the org filter.
    $this->saveSubscription($otherOrg, self::OTHER_ORGANIZATION_ID, 'https://hooks.example.test/e-other', ['intervention.published'], true, '2026-07-04T00:00:00+00:00');
    $this->entityManager->clear();

    $matches = $this->repository->findActiveByOrganizationAndEventType(self::ORGANIZATION_ID, 'intervention.published');

    self::assertCount(1, $matches);
    self::assertSame($matching, (string) $matches[0]->id());
    self::assertTrue($matches[0]->subscribesTo('intervention.published'));

    // No active subscription is registered for an unknown event type.
    self::assertSame(
      [],
      $this->repository->findActiveByOrganizationAndEventType(self::ORGANIZATION_ID, 'equipment.retired'),
    );
  }

  /**
   * Persists a reconstituted subscription with a controlled createdAt through
   * the repository under test.
   *
   * @param string $id the subscription identifier
   * @param string $organizationId the owning organization identifier
   * @param string $url the target URL
   * @param list<string> $eventTypes the subscribed public event type allowlist
   * @param bool $isActive whether deliveries are currently enqueued
   * @param string $createdAt the ISO-8601 creation timestamp
   */
  private function saveSubscription(
    string $id,
    string $organizationId,
    string $url,
    array $eventTypes,
    bool $isActive,
    string $createdAt,
  ): void {
    $timestamp = new DateTimeImmutable($createdAt);
    $subscription = WebhookSubscription::reconstitute(
      id: WebhookSubscriptionId::fromString($id),
      organizationId: $organizationId,
      url: $url,
      secretCiphertext: 'cipher-' . $id,
      eventTypes: $eventTypes,
      isActive: $isActive,
      description: '',
      createdAt: $timestamp,
      updatedAt: $timestamp,
    );

    $this->repository->save($subscription);
  }

  private function createOrganization(string $id, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Webhook Subscription Repository Test';
    $organization->slug = $slug;
    $organization->ownerUserId = 'b1c2d3e4-0000-4000-8000-000000009000';
    $organization->createdByUserId = 'b1c2d3e4-0000-4000-8000-000000009000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM webhook_subscriptions WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM webhook_subscriptions WHERE organization_id = :organizationId',
      ['organizationId' => self::OTHER_ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::OTHER_ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
