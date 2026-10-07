<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Adapter\MaintenanceExport;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Adapter\MaintenanceExport\MaintenanceExportSiteIdentityAdapter;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Class MaintenanceExportSiteIdentityAdapterTest
 *
 * Verifies retained root-site identities and excludes other facilities and unpublished drafts.
 *
 * @category Integration Tests
 */
#[CoversClass(MaintenanceExportSiteIdentityAdapter::class)]
final class MaintenanceExportSiteIdentityAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'd17e8400-e29b-41d4-a716-446655780001';

  private const string OTHER_ORGANIZATION_ID = 'd17e8400-e29b-41d4-a716-446655780002';

  private const string SITE_ID = 'd17e8400-e29b-41d4-a716-446655780010';

  private const string ARCHIVED_ID = 'd17e8400-e29b-41d4-a716-446655780011';

  private const string DRAFT_ID = 'd17e8400-e29b-41d4-a716-446655780012';

  private const string BUILDING_ID = 'd17e8400-e29b-41d4-a716-446655780013';

  private const string NESTED_SITE_ID = 'd17e8400-e29b-41d4-a716-446655780014';

  private EntityManagerInterface $main;

  private MaintenanceExportSiteIdentityAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    $main = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $main);
    $this->main = $main;
    $this->adapter = new MaintenanceExportSiteIdentityAdapter($main);
    $this->organization(self::ORGANIZATION_ID, 'export-site-owner');
    $this->organization(self::OTHER_ORGANIZATION_ID, 'export-site-other');
    $main->flush();
  }

  #[Test]
  public function retainsPublishedActiveAndArchivedRootSites(): void
  {
    $this->facility(self::SITE_ID);
    $this->facility(self::ARCHIVED_ID, status: 'archived');
    $this->main->flush();
    $this->main->clear();

    self::assertTrue($this->adapter->exists(self::ORGANIZATION_ID, 'site', self::SITE_ID));
    self::assertTrue($this->adapter->exists(self::ORGANIZATION_ID, 'site', self::ARCHIVED_ID));
  }

  #[Test]
  public function refusesAnUnpublishedDraftEvenForItsOwner(): void
  {
    $this->facility(self::DRAFT_ID, recordStatus: 'draft');
    $this->main->flush();
    $this->main->clear();

    self::assertFalse($this->adapter->exists(self::ORGANIZATION_ID, 'site', self::DRAFT_ID));
  }

  #[Test]
  public function refusesBuildingsAndSitesThatAreNotRoots(): void
  {
    $site = $this->facility(self::SITE_ID);
    $this->facility(self::BUILDING_ID, type: 'building', parent: $site);
    $this->facility(self::NESTED_SITE_ID, parent: $site);
    $this->main->flush();
    $this->main->clear();

    self::assertTrue($this->adapter->exists(self::ORGANIZATION_ID, 'site', self::SITE_ID));
    self::assertFalse($this->adapter->exists(self::ORGANIZATION_ID, 'site', self::BUILDING_ID));
    self::assertFalse($this->adapter->exists(self::ORGANIZATION_ID, 'site', self::NESTED_SITE_ID));
  }

  #[Test]
  public function returnsTheSameFalseForForeignAndUnknownIdentities(): void
  {
    $this->facility(self::SITE_ID, self::OTHER_ORGANIZATION_ID);
    $this->main->flush();
    $this->main->clear();

    self::assertFalse($this->adapter->exists(self::ORGANIZATION_ID, 'site', self::SITE_ID));
    self::assertFalse($this->adapter->exists(self::ORGANIZATION_ID, 'site', self::ARCHIVED_ID));
    self::assertTrue($this->adapter->exists(self::OTHER_ORGANIZATION_ID, 'site', self::SITE_ID));
  }

  #[Test]
  public function neverTreatsAnotherResourceTypeAsASite(): void
  {
    $this->facility(self::SITE_ID);
    $this->main->flush();

    foreach (['customer', 'equipment', 'SITE', 'unknown'] as $type) {
      self::assertFalse($this->adapter->exists(self::ORGANIZATION_ID, $type, self::SITE_ID));
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

  private function facility(string $id, string $organizationId = self::ORGANIZATION_ID, string $status = 'active', string $recordStatus = 'published', string $type = 'site', ?FacilityRecord $parent = null): FacilityRecord
  {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $this->main->getReference(OrganizationRecord::class, $organizationId);
    $facility->type = $type;
    $facility->name = 'Retained site';
    $facility->status = $status;
    $facility->recordStatus = $recordStatus;
    $facility->parentFacility = $parent;
    $facility->createdAt = new DateTimeImmutable('2026-10-07T08:00:00+00:00');
    $facility->updatedAt = $facility->createdAt;
    $this->main->persist($facility);

    return $facility;
  }
}
