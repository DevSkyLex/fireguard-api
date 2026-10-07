<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Adapter\MaintenanceExport;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Adapter\MaintenanceExport\MaintenanceExportEquipmentIdentityAdapter;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Class MaintenanceExportEquipmentIdentityAdapterTest
 *
 * Verifies historical published asset validation and draft isolation on PostgreSQL.
 *
 * @category Integration Tests
 */
#[CoversClass(MaintenanceExportEquipmentIdentityAdapter::class)]
final class MaintenanceExportEquipmentIdentityAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'd17e8400-e29b-41d4-a716-446655770001';

  private const string OTHER_ORGANIZATION_ID = 'd17e8400-e29b-41d4-a716-446655770002';

  private const string EQUIPMENT_ID = 'd17e8400-e29b-41d4-a716-446655770010';

  private const string RETIRED_ID = 'd17e8400-e29b-41d4-a716-446655770011';

  private const string DRAFT_ID = 'd17e8400-e29b-41d4-a716-446655770012';

  private EntityManagerInterface $main;

  private MaintenanceExportEquipmentIdentityAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    $main = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $main);
    $this->main = $main;
    $this->adapter = new MaintenanceExportEquipmentIdentityAdapter($main);
    $this->organization(self::ORGANIZATION_ID, 'export-asset-owner');
    $this->organization(self::OTHER_ORGANIZATION_ID, 'export-asset-other');
    $main->flush();
  }

  #[Test]
  public function retainsPublishedOperationalAndRetiredEquipment(): void
  {
    $this->equipment(self::EQUIPMENT_ID);
    $this->equipment(self::RETIRED_ID, status: 'decommissioned');
    $this->main->flush();
    $this->main->clear();

    self::assertTrue($this->adapter->exists(self::ORGANIZATION_ID, 'equipment', self::EQUIPMENT_ID));
    self::assertTrue($this->adapter->exists(self::ORGANIZATION_ID, 'equipment', self::RETIRED_ID));
  }

  #[Test]
  public function refusesAnUnpublishedDraftEvenForItsOwner(): void
  {
    $this->equipment(self::DRAFT_ID, recordStatus: 'draft');
    $this->main->flush();
    $this->main->clear();

    self::assertFalse($this->adapter->exists(self::ORGANIZATION_ID, 'equipment', self::DRAFT_ID));
  }

  #[Test]
  public function returnsTheSameFalseForForeignAndUnknownIdentities(): void
  {
    $this->equipment(self::EQUIPMENT_ID, self::OTHER_ORGANIZATION_ID);
    $this->main->flush();
    $this->main->clear();

    self::assertFalse($this->adapter->exists(self::ORGANIZATION_ID, 'equipment', self::EQUIPMENT_ID));
    self::assertFalse($this->adapter->exists(self::ORGANIZATION_ID, 'equipment', self::RETIRED_ID));
    self::assertTrue($this->adapter->exists(self::OTHER_ORGANIZATION_ID, 'equipment', self::EQUIPMENT_ID));
  }

  #[Test]
  public function neverTreatsAnotherResourceTypeAsEquipment(): void
  {
    $this->equipment(self::EQUIPMENT_ID);
    $this->main->flush();

    foreach (['site', 'customer', 'EQUIPMENT', 'unknown'] as $type) {
      self::assertFalse($this->adapter->exists(self::ORGANIZATION_ID, $type, self::EQUIPMENT_ID));
    }
  }

  private function organization(string $id, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Export identity test';
    $organization->slug = $slug;
    $organization->ownerUserId = self::ORGANIZATION_ID;
    $organization->createdByUserId = self::ORGANIZATION_ID;
    $organization->createdAt = new DateTimeImmutable('2026-10-07T08:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->main->persist($organization);
  }

  private function equipment(string $id, string $organizationId = self::ORGANIZATION_ID, string $status = 'operational', string $recordStatus = 'published'): void
  {
    $equipment = new EquipmentRecord();
    $equipment->id = $id;
    $equipment->organization = $this->main->getReference(OrganizationRecord::class, $organizationId);
    $equipment->type = 'fire_extinguisher';
    $equipment->status = $status;
    $equipment->recordStatus = $recordStatus;
    $equipment->createdAt = new DateTimeImmutable('2026-10-07T08:00:00+00:00');
    $equipment->updatedAt = $equipment->createdAt;
    $this->main->persist($equipment);
  }
}
