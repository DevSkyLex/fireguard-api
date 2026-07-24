<?php

declare(strict_types=1);

namespace Tests\Integration\Billing\Infrastructure\Persistence\Doctrine\Repository;

use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\{BillingInterval, SubscriptionId, SubscriptionStatus};
use Billing\Infrastructure\Persistence\Doctrine\Repository\SubscriptionRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test SubscriptionRepositoryTest.
 *
 * Exercises the real Doctrine round-trip behind `SubscriptionRepository` against
 * a live main entity manager. `SubscriptionRecord.organizationId` is a plain
 * string column (no `organizations` FK), so no cross-module setup is needed. The
 * insert/update branch selection inside `save()` and the `findOneBy` lookups by
 * organization and Stripe customer are covered.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SubscriptionRepository::class)]
final class SubscriptionRepositoryTest extends KernelTestCase
{
  private const string SUBSCRIPTION_ROUNDTRIP_ID = 'aa0e8400-e29b-41d4-a716-4466554b0001';

  private const string SUBSCRIPTION_UPDATE_ID = 'aa0e8400-e29b-41d4-a716-4466554b0002';

  private const string SUBSCRIPTION_CUSTOMER_ID = 'aa0e8400-e29b-41d4-a716-4466554b0003';

  private const string ORGANIZATION_ROUNDTRIP_ID = 'bb0e8400-e29b-41d4-a716-4466554b1001';

  private const string ORGANIZATION_UPDATE_ID = 'bb0e8400-e29b-41d4-a716-4466554b1002';

  private const string ORGANIZATION_CUSTOMER_ID = 'bb0e8400-e29b-41d4-a716-4466554b1003';

  private const string ORGANIZATION_ABSENT_ID = 'bb0e8400-e29b-41d4-a716-4466554b1004';

  private EntityManagerInterface $entityManager;

  private SubscriptionRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->repository = new SubscriptionRepository($this->entityManager);

    $this->cleanup();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveThenFindByOrganizationIdRoundTrips(): void
  {
    $subscription = Subscription::start(
      id: SubscriptionId::fromString(self::SUBSCRIPTION_ROUNDTRIP_ID),
      organizationId: self::ORGANIZATION_ROUNDTRIP_ID,
      stripeCustomerId: 'cus_roundtrip_001',
    );

    $this->repository->save($subscription);
    $this->entityManager->clear();

    $found = $this->repository->findByOrganizationId(self::ORGANIZATION_ROUNDTRIP_ID);

    self::assertNotNull($found);
    self::assertSame(self::SUBSCRIPTION_ROUNDTRIP_ID, (string) $found->id());
    self::assertSame(self::ORGANIZATION_ROUNDTRIP_ID, $found->organizationId());
    self::assertSame('cus_roundtrip_001', $found->stripeCustomerId());
    self::assertSame(SubscriptionStatus::INCOMPLETE, $found->status());
    self::assertFalse($found->cancelAtPeriodEnd());
  }

  #[Test]
  public function testFindByStripeCustomerIdReturnsTheSubscription(): void
  {
    $subscription = Subscription::start(
      id: SubscriptionId::fromString(self::SUBSCRIPTION_CUSTOMER_ID),
      organizationId: self::ORGANIZATION_CUSTOMER_ID,
      stripeCustomerId: 'cus_lookup_777',
    );

    $this->repository->save($subscription);
    $this->entityManager->clear();

    $found = $this->repository->findByStripeCustomerId('cus_lookup_777');

    self::assertNotNull($found);
    self::assertSame(self::SUBSCRIPTION_CUSTOMER_ID, (string) $found->id());
    self::assertSame('cus_lookup_777', $found->stripeCustomerId());
  }

  #[Test]
  public function testFindByOrganizationIdReturnsNullWhenAbsent(): void
  {
    self::assertNull($this->repository->findByOrganizationId(self::ORGANIZATION_ABSENT_ID));
  }

  #[Test]
  public function testFindByStripeCustomerIdReturnsNullWhenAbsent(): void
  {
    self::assertNull($this->repository->findByStripeCustomerId('cus_does_not_exist'));
  }

  #[Test]
  public function testSaveUpdatesTheExistingSubscription(): void
  {
    $subscription = Subscription::start(
      id: SubscriptionId::fromString(self::SUBSCRIPTION_UPDATE_ID),
      organizationId: self::ORGANIZATION_UPDATE_ID,
      stripeCustomerId: 'cus_update_555',
    );
    $this->repository->save($subscription);
    $this->entityManager->clear();

    // Reload, mutate through the Stripe sync path, and save again: this must take
    // the update branch of save() (find() returns the existing record) rather than
    // inserting a second row that would breach the per-organization unique index.
    $reloaded = $this->repository->findByOrganizationId(self::ORGANIZATION_UPDATE_ID);
    self::assertNotNull($reloaded);

    $reloaded->syncFromStripe(
      stripeSubscriptionId: 'sub_update_555',
      status: SubscriptionStatus::ACTIVE,
      planKey: 'pro_monthly',
      interval: BillingInterval::MONTH,
      currentPeriodEnd: new DateTimeImmutable('2026-09-01T00:00:00+00:00'),
      cancelAtPeriodEnd: true,
    );
    $this->repository->save($reloaded);
    $this->entityManager->clear();

    $found = $this->repository->findByOrganizationId(self::ORGANIZATION_UPDATE_ID);

    self::assertNotNull($found);
    self::assertSame(self::SUBSCRIPTION_UPDATE_ID, (string) $found->id());
    self::assertSame('sub_update_555', $found->stripeSubscriptionId());
    self::assertSame(SubscriptionStatus::ACTIVE, $found->status());
    self::assertSame('pro_monthly', $found->planKey());
    self::assertSame(BillingInterval::MONTH, $found->interval());
    self::assertTrue($found->cancelAtPeriodEnd());
    self::assertNotNull($found->currentPeriodEnd());
    self::assertSame('2026-09-01', $found->currentPeriodEnd()->format('Y-m-d'));
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM billing_subscriptions WHERE organization_id IN (:roundtrip, :update, :customer, :absent)',
      [
        'roundtrip' => self::ORGANIZATION_ROUNDTRIP_ID,
        'update' => self::ORGANIZATION_UPDATE_ID,
        'customer' => self::ORGANIZATION_CUSTOMER_ID,
        'absent' => self::ORGANIZATION_ABSENT_ID,
      ],
    );
    $this->entityManager->clear();
  }
}
