<?php

declare(strict_types=1);

namespace Tests\Integration\Billing\Infrastructure\Persistence\Doctrine\Mapper;

use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\{BillingInterval, SubscriptionId, SubscriptionStatus};
use Billing\Infrastructure\Persistence\Doctrine\Mapper\SubscriptionMapper;
use Billing\Infrastructure\Persistence\Doctrine\Record\SubscriptionRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test SubscriptionMapperTest.
 *
 * Covers the static `SubscriptionMapper` against genuinely Doctrine-hydrated
 * records on a live main entity manager. `SubscriptionRecord.organizationId` is a
 * plain string column (no `organizations` FK), so no cross-module graph is needed.
 * The mapper is a stateless static helper — it is exercised directly rather than
 * fetched from the container. Every branch is driven: `toDomain` with a known
 * status and non-null interval versus an unknown status (falling back to
 * `INCOMPLETE`) and a null interval; `toRecord` building a fresh record from an
 * aggregate; and `applyTo` overwriting an existing managed record, clearing the
 * optional columns.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SubscriptionMapper::class)]
final class SubscriptionMapperTest extends KernelTestCase
{
  private const string SUBSCRIPTION_FULL_ID = 'ac0e8400-e29b-41d4-a716-4466554c0001';

  private const string SUBSCRIPTION_MINIMAL_ID = 'ac0e8400-e29b-41d4-a716-4466554c0002';

  private const string SUBSCRIPTION_TO_RECORD_ID = 'ac0e8400-e29b-41d4-a716-4466554c0003';

  private const string SUBSCRIPTION_APPLY_ID = 'ac0e8400-e29b-41d4-a716-4466554c0004';

  private const string ORGANIZATION_FULL_ID = 'bc0e8400-e29b-41d4-a716-4466554c1001';

  private const string ORGANIZATION_MINIMAL_ID = 'bc0e8400-e29b-41d4-a716-4466554c1002';

  private const string ORGANIZATION_TO_RECORD_ID = 'bc0e8400-e29b-41d4-a716-4466554c1003';

  private const string ORGANIZATION_APPLY_ID = 'bc0e8400-e29b-41d4-a716-4466554c1004';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testToDomainMapsAKnownStatusAndNonNullInterval(): void
  {
    $record = $this->buildRecord(
      id: self::SUBSCRIPTION_FULL_ID,
      organizationId: self::ORGANIZATION_FULL_ID,
      stripeCustomerId: 'cus_full_001',
      status: 'active',
      interval: 'month',
      stripeSubscriptionId: 'sub_full_001',
      planKey: 'pro_monthly',
      currentPeriodEnd: new DateTimeImmutable('2026-08-15 10:30:00'),
      cancelAtPeriodEnd: true,
      createdAt: new DateTimeImmutable('2026-01-02 08:00:00'),
      updatedAt: new DateTimeImmutable('2026-03-04 09:15:00'),
    );
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $reloaded = $this->entityManager->find(SubscriptionRecord::class, self::SUBSCRIPTION_FULL_ID);
    self::assertInstanceOf(SubscriptionRecord::class, $reloaded);

    $subscription = SubscriptionMapper::toDomain($reloaded);

    self::assertSame(self::SUBSCRIPTION_FULL_ID, (string) $subscription->id());
    self::assertSame(self::ORGANIZATION_FULL_ID, $subscription->organizationId());
    self::assertSame('cus_full_001', $subscription->stripeCustomerId());
    self::assertSame('sub_full_001', $subscription->stripeSubscriptionId());
    self::assertSame(SubscriptionStatus::ACTIVE, $subscription->status());
    self::assertSame('pro_monthly', $subscription->planKey());
    self::assertSame(BillingInterval::MONTH, $subscription->interval());
    self::assertNotNull($subscription->currentPeriodEnd());
    self::assertSame('2026-08-15 10:30:00', $subscription->currentPeriodEnd()->format('Y-m-d H:i:s'));
    self::assertTrue($subscription->cancelAtPeriodEnd());
    self::assertSame('2026-01-02 08:00:00', $subscription->createdAt()->format('Y-m-d H:i:s'));
    self::assertSame('2026-03-04 09:15:00', $subscription->updatedAt()->format('Y-m-d H:i:s'));
  }

  #[Test]
  public function testToDomainFallsBackToIncompleteForAnUnknownStatusAndNullInterval(): void
  {
    $record = $this->buildRecord(
      id: self::SUBSCRIPTION_MINIMAL_ID,
      organizationId: self::ORGANIZATION_MINIMAL_ID,
      stripeCustomerId: 'cus_minimal_002',
      status: 'ghost_status',
      interval: null,
      stripeSubscriptionId: null,
      planKey: null,
      currentPeriodEnd: null,
      cancelAtPeriodEnd: false,
      createdAt: new DateTimeImmutable('2026-04-01 00:00:00'),
      updatedAt: new DateTimeImmutable('2026-04-01 00:00:00'),
    );
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $reloaded = $this->entityManager->find(SubscriptionRecord::class, self::SUBSCRIPTION_MINIMAL_ID);
    self::assertInstanceOf(SubscriptionRecord::class, $reloaded);

    $subscription = SubscriptionMapper::toDomain($reloaded);

    self::assertSame(SubscriptionStatus::INCOMPLETE, $subscription->status());
    self::assertNull($subscription->interval());
    self::assertNull($subscription->stripeSubscriptionId());
    self::assertNull($subscription->planKey());
    self::assertNull($subscription->currentPeriodEnd());
    self::assertFalse($subscription->cancelAtPeriodEnd());
  }

  #[Test]
  public function testToRecordBuildsAPersistableRecordFromTheAggregate(): void
  {
    $subscription = Subscription::reconstitute(
      id: SubscriptionId::fromString(self::SUBSCRIPTION_TO_RECORD_ID),
      organizationId: self::ORGANIZATION_TO_RECORD_ID,
      stripeCustomerId: 'cus_torecord_003',
      status: SubscriptionStatus::TRIALING,
      createdAt: new DateTimeImmutable('2026-02-01 00:00:00'),
      updatedAt: new DateTimeImmutable('2026-02-10 12:00:00'),
      stripeSubscriptionId: 'sub_torecord_003',
      planKey: 'team_yearly',
      interval: BillingInterval::YEAR,
      currentPeriodEnd: new DateTimeImmutable('2027-02-01 00:00:00'),
      cancelAtPeriodEnd: true,
    );

    $record = SubscriptionMapper::toRecord($subscription);

    self::assertSame(self::SUBSCRIPTION_TO_RECORD_ID, $record->id);
    self::assertSame('trialing', $record->status);
    self::assertSame('year', $record->interval);

    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $reloaded = $this->entityManager->find(SubscriptionRecord::class, self::SUBSCRIPTION_TO_RECORD_ID);
    self::assertInstanceOf(SubscriptionRecord::class, $reloaded);

    self::assertSame(self::ORGANIZATION_TO_RECORD_ID, $reloaded->organizationId);
    self::assertSame('cus_torecord_003', $reloaded->stripeCustomerId);
    self::assertSame('sub_torecord_003', $reloaded->stripeSubscriptionId);
    self::assertSame('trialing', $reloaded->status);
    self::assertSame('team_yearly', $reloaded->planKey);
    self::assertSame('year', $reloaded->interval);
    self::assertNotNull($reloaded->currentPeriodEnd);
    self::assertSame('2027-02-01 00:00:00', $reloaded->currentPeriodEnd->format('Y-m-d H:i:s'));
    self::assertTrue($reloaded->cancelAtPeriodEnd);
    self::assertSame('2026-02-01 00:00:00', $reloaded->createdAt->format('Y-m-d H:i:s'));
    self::assertSame('2026-02-10 12:00:00', $reloaded->updatedAt->format('Y-m-d H:i:s'));
  }

  #[Test]
  public function testApplyToOverwritesAnExistingRecordAndClearsOptionalColumns(): void
  {
    $initial = $this->buildRecord(
      id: self::SUBSCRIPTION_APPLY_ID,
      organizationId: self::ORGANIZATION_APPLY_ID,
      stripeCustomerId: 'cus_apply_004',
      status: 'active',
      interval: 'month',
      stripeSubscriptionId: 'sub_apply_004',
      planKey: 'pro_monthly',
      currentPeriodEnd: new DateTimeImmutable('2026-08-01 00:00:00'),
      cancelAtPeriodEnd: true,
      createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01 00:00:00'),
    );
    $this->entityManager->persist($initial);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $existing = $this->entityManager->find(SubscriptionRecord::class, self::SUBSCRIPTION_APPLY_ID);
    self::assertInstanceOf(SubscriptionRecord::class, $existing);

    // Canceled aggregate: interval and every optional field are cleared, so applyTo
    // must null the corresponding columns on the already-managed record and flush an
    // UPDATE rather than an INSERT.
    $updated = Subscription::reconstitute(
      id: SubscriptionId::fromString(self::SUBSCRIPTION_APPLY_ID),
      organizationId: self::ORGANIZATION_APPLY_ID,
      stripeCustomerId: 'cus_apply_004',
      status: SubscriptionStatus::CANCELED,
      createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
      updatedAt: new DateTimeImmutable('2026-05-05 05:05:05'),
      stripeSubscriptionId: null,
      planKey: null,
      interval: null,
      currentPeriodEnd: null,
      cancelAtPeriodEnd: false,
    );

    SubscriptionMapper::applyTo($existing, $updated);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $reloaded = $this->entityManager->find(SubscriptionRecord::class, self::SUBSCRIPTION_APPLY_ID);
    self::assertInstanceOf(SubscriptionRecord::class, $reloaded);

    self::assertSame('canceled', $reloaded->status);
    self::assertNull($reloaded->interval);
    self::assertNull($reloaded->stripeSubscriptionId);
    self::assertNull($reloaded->planKey);
    self::assertNull($reloaded->currentPeriodEnd);
    self::assertFalse($reloaded->cancelAtPeriodEnd);
    self::assertSame('2026-05-05 05:05:05', $reloaded->updatedAt->format('Y-m-d H:i:s'));
  }

  private function buildRecord(
    string $id,
    string $organizationId,
    string $stripeCustomerId,
    string $status,
    ?string $interval,
    ?string $stripeSubscriptionId,
    ?string $planKey,
    ?DateTimeImmutable $currentPeriodEnd,
    bool $cancelAtPeriodEnd,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
  ): SubscriptionRecord {
    $record = new SubscriptionRecord();
    $record->id = $id;
    $record->organizationId = $organizationId;
    $record->stripeCustomerId = $stripeCustomerId;
    $record->status = $status;
    $record->interval = $interval;
    $record->stripeSubscriptionId = $stripeSubscriptionId;
    $record->planKey = $planKey;
    $record->currentPeriodEnd = $currentPeriodEnd;
    $record->cancelAtPeriodEnd = $cancelAtPeriodEnd;
    $record->createdAt = $createdAt;
    $record->updatedAt = $updatedAt;

    return $record;
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM billing_subscriptions WHERE organization_id IN (:full, :minimal, :toRecord, :apply)',
      [
        'full' => self::ORGANIZATION_FULL_ID,
        'minimal' => self::ORGANIZATION_MINIMAL_ID,
        'toRecord' => self::ORGANIZATION_TO_RECORD_ID,
        'apply' => self::ORGANIZATION_APPLY_ID,
      ],
    );
    $this->entityManager->clear();
  }
}
