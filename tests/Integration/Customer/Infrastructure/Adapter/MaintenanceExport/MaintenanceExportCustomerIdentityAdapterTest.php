<?php

declare(strict_types=1);

namespace Tests\Integration\Customer\Infrastructure\Adapter\MaintenanceExport;

use Customer\Infrastructure\Adapter\MaintenanceExport\MaintenanceExportCustomerIdentityAdapter;
use Customer\Infrastructure\Persistence\Doctrine\Record\CustomerRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Class MaintenanceExportCustomerIdentityAdapterTest
 *
 * Verifies retained client identities on PostgreSQL without exposing client contact data.
 *
 * @category Integration Tests
 */
#[CoversClass(MaintenanceExportCustomerIdentityAdapter::class)]
final class MaintenanceExportCustomerIdentityAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'd17e8400-e29b-41d4-a716-446655760001';

  private const string OTHER_ORGANIZATION_ID = 'd17e8400-e29b-41d4-a716-446655760002';

  private const string CUSTOMER_ID = 'd17e8400-e29b-41d4-a716-446655760010';

  private const string ARCHIVED_ID = 'd17e8400-e29b-41d4-a716-446655760011';

  private EntityManagerInterface $main;

  private MaintenanceExportCustomerIdentityAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    $main = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $main);
    $this->main = $main;
    $this->adapter = new MaintenanceExportCustomerIdentityAdapter($main);
  }

  #[Test]
  public function retainsActiveAndArchivedCustomerIdentities(): void
  {
    $this->customer(self::CUSTOMER_ID);
    $this->customer(self::ARCHIVED_ID, archived: true);
    $this->main->flush();
    $this->main->clear();

    self::assertTrue($this->adapter->exists(self::ORGANIZATION_ID, 'customer', self::CUSTOMER_ID));
    self::assertTrue($this->adapter->exists(self::ORGANIZATION_ID, 'customer', self::ARCHIVED_ID));
  }

  #[Test]
  public function returnsTheSameFalseForForeignAndUnknownIdentities(): void
  {
    $this->customer(self::CUSTOMER_ID, self::OTHER_ORGANIZATION_ID);
    $this->main->flush();
    $this->main->clear();

    self::assertFalse($this->adapter->exists(self::ORGANIZATION_ID, 'customer', self::CUSTOMER_ID));
    self::assertFalse($this->adapter->exists(self::ORGANIZATION_ID, 'customer', self::ARCHIVED_ID));
    self::assertTrue($this->adapter->exists(self::OTHER_ORGANIZATION_ID, 'customer', self::CUSTOMER_ID));
  }

  #[Test]
  public function neverTreatsAnotherResourceTypeAsACustomer(): void
  {
    $this->customer(self::CUSTOMER_ID);
    $this->main->flush();

    foreach (['site', 'equipment', 'CUSTOMER', 'unknown'] as $type) {
      self::assertFalse($this->adapter->exists(self::ORGANIZATION_ID, $type, self::CUSTOMER_ID));
    }
  }

  private function customer(string $id, string $organizationId = self::ORGANIZATION_ID, bool $archived = false): void
  {
    $customer = new CustomerRecord();
    $customer->id = $id;
    $customer->organizationId = $organizationId;
    $customer->name = 'Retained internal client';
    $customer->email = 'private@example.com';
    $customer->contacts = [['name' => 'Private contact', 'email' => 'contact@example.com', 'phone' => null, 'role' => null]];
    $customer->createdAt = new DateTimeImmutable('2026-10-07T08:00:00+00:00');
    $customer->updatedAt = $customer->createdAt;
    $customer->archivedAt = $archived ? $customer->createdAt : null;
    $this->main->persist($customer);
  }
}
