<?php

declare(strict_types=1);

namespace Tests\Integration\Billing\Infrastructure\Persistence\Doctrine;

use Billing\Application\Contract\Stripe\StripeEvent;
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\SubscriptionId;
use Billing\Infrastructure\Persistence\Doctrine\Lock\PostgresBillingReconciliationAdapter;
use Billing\Infrastructure\Persistence\Doctrine\Repository\SubscriptionRepository;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Real PostgreSQL locks, rollback and receipt replay across independent workers.
 *
 * @category Integration Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class BillingReconciliationConcurrencyTest extends KernelTestCase
{
  private const string ORG = 'b9000000-0000-4000-8000-000000000071';

  private const string ID = 'b9000000-0000-4000-8000-000000000072';

  private Connection $a;

  private Connection $b;

  private EntityManagerInterface $emA;

  private EntityManagerInterface $emB;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $configured */
    $configured = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->a = DriverManager::getConnection(['url' => $url]);
    $this->b = DriverManager::getConnection(['url' => $url]);
    $this->emA = new EntityManager($this->a, $configured->getConfiguration());
    $this->emB = new EntityManager($this->b, $configured->getConfiguration());
    $this->clearRows();
  }

  protected function tearDown(): void
  {
    foreach ([$this->a, $this->b] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->clearRows();
    $this->emA->close();
    $this->emB->close();
    $this->a->close();
    $this->b->close();
    parent::tearDown();
  }

  #[Test]
  public function serializesEvenTheFirstSubscriptionForAnOrganization(): void
  {
    $this->b->executeStatement("SET lock_timeout = '150ms'");
    $entered = false;
    $blocked = false;
    new PostgresBillingReconciliationAdapter($this->emA)->synchronized(self::ORG, function () use (&$entered, &$blocked): void {
      try {
        new PostgresBillingReconciliationAdapter($this->emB)->synchronized(self::ORG, static function () use (&$entered): void {
          $entered = true;
        });
      } catch (DriverException $exception) {
        self::assertSame('55P03', $exception->getSQLState());
        $blocked = true;
      }
    });
    self::assertTrue($blocked);
    self::assertFalse($entered);
  }

  #[Test]
  public function commitsTheProjectionAndReceiptOnceAcrossWorkers(): void
  {
    $calls = 0;
    $event = $this->event();
    new PostgresBillingReconciliationAdapter($this->emA)->processEvent(self::ORG, $event, function () use (&$calls): void {
      ++$calls;
      new SubscriptionRepository($this->emA)->save(Subscription::start(new SubscriptionId(self::ID), self::ORG, 'cus_concurrency'));
    });
    new PostgresBillingReconciliationAdapter($this->emB)->processEvent(self::ORG, $event, static function () use (&$calls): void {
      ++$calls;
    });
    self::assertSame(1, $calls);
    self::assertSame(1, $this->b->fetchOne('SELECT COUNT(*) FROM billing_stripe_events WHERE organization_id = ?', [self::ORG]));
    self::assertSame('cus_concurrency', $this->b->fetchOne('SELECT stripe_customer_id FROM billing_subscriptions WHERE id = ?', [self::ID]));
  }

  #[Test]
  public function rollsBackAndAllowsAnotherWorkerToRetryAfterAPlanAssignmentFailure(): void
  {
    try {
      new PostgresBillingReconciliationAdapter($this->emA)->processEvent(self::ORG, $this->event(), function (): never {
        new SubscriptionRepository($this->emA)->save(Subscription::start(new SubscriptionId(self::ID), self::ORG, 'cus_concurrency'));

        throw new RuntimeException('Plan assignment failed');
      });
    } catch (RuntimeException $exception) {
      self::assertSame('Plan assignment failed', $exception->getMessage());
    }
    self::assertSame(0, $this->b->fetchOne('SELECT COUNT(*) FROM billing_subscriptions WHERE id = ?', [self::ID]));
    self::assertSame(0, $this->b->fetchOne('SELECT COUNT(*) FROM billing_stripe_events WHERE organization_id = ?', [self::ORG]));

    new PostgresBillingReconciliationAdapter($this->emB)->processEvent(self::ORG, $this->event(), function (): void {
      new SubscriptionRepository($this->emB)->save(Subscription::start(new SubscriptionId(self::ID), self::ORG, 'cus_concurrency'));
    });
    self::assertSame(1, $this->a->fetchOne('SELECT COUNT(*) FROM billing_stripe_events WHERE organization_id = ?', [self::ORG]));
  }

  #[Test]
  public function refreshesTheLocalProjectionAfterWaitingForTheLock(): void
  {
    new SubscriptionRepository($this->emA)->save(Subscription::start(new SubscriptionId(self::ID), self::ORG, 'cus_concurrency'));
    $reader = new SubscriptionRepository($this->emB);
    self::assertSame('incomplete', $reader->findByOrganizationId(self::ORG)?->status()->value);
    $this->a->update('billing_subscriptions', ['status' => 'active'], ['id' => self::ID]);

    new PostgresBillingReconciliationAdapter($this->emB)->synchronized(self::ORG, static function () use ($reader): void {
      self::assertSame('active', $reader->findByOrganizationId(self::ORG, refresh: true)?->status()->value);
    });
  }

  private function clearRows(): void
  {
    $this->a->delete('billing_stripe_events', ['organization_id' => self::ORG]);
    $this->a->delete('billing_subscriptions', ['id' => self::ID]);
  }

  private function event(): StripeEvent
  {
    return new StripeEvent(type: 'customer.subscription.updated', eventId: 'evt_billing_concurrency', created: 1);
  }
}
