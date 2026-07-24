<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Adapter\Organization;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Adapter\Organization\FacilityStatisticsAdapter;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test FacilityStatisticsAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityStatisticsAdapter::class)]
final class FacilityStatisticsAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554a4000';

  private const string OTHER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554a4001';

  private EntityManagerInterface $entityManager;

  private FacilityStatisticsAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->adapter = new FacilityStatisticsAdapter(new FacilityRepository($this->entityManager));

    $this->removeOrganization(self::ORGANIZATION_ID);
    $this->removeOrganization(self::OTHER_ORGANIZATION_ID);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testCountsFacilitiesWithAndWithoutArchivedScopedToOrganization(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-stats-owner');
    $otherOrganization = $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'facility-stats-other');

    $this->createFacility('550e8400-e29b-41d4-a716-4466554a4010', $organization, 'site', 'active');
    $this->createFacility('550e8400-e29b-41d4-a716-4466554a4011', $organization, 'building', 'active');
    $this->createFacility('550e8400-e29b-41d4-a716-4466554a4012', $organization, 'site', 'archived');
    $this->createFacility('550e8400-e29b-41d4-a716-4466554a4013', $otherOrganization, 'site', 'active');

    $this->entityManager->flush();
    $this->entityManager->clear();

    // countFacilities includes archived; countActiveFacilities excludes them.
    self::assertSame(3, $this->adapter->countFacilities(self::ORGANIZATION_ID));
    self::assertSame(2, $this->adapter->countActiveFacilities(self::ORGANIZATION_ID));
    // Tenant isolation: the other organization's facility is never counted.
    self::assertSame(1, $this->adapter->countFacilities(self::OTHER_ORGANIZATION_ID));
  }

  #[Test]
  public function testCountsFacilitiesByTypeReturnsEveryTypeKey(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-stats-by-type');

    $this->createFacility('550e8400-e29b-41d4-a716-4466554a4020', $organization, 'site', 'active');
    $this->createFacility('550e8400-e29b-41d4-a716-4466554a4021', $organization, 'site', 'active');
    $this->createFacility('550e8400-e29b-41d4-a716-4466554a4022', $organization, 'building', 'archived');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $counts = $this->adapter->countFacilitiesByType(self::ORGANIZATION_ID);

    // Every FacilityType is normalized to a key, defaulting to zero.
    self::assertSame(2, $counts['site']);
    self::assertSame(1, $counts['building']);
    self::assertSame(0, $counts['floor']);
    self::assertSame(0, $counts['zone']);
    self::assertSame(0, $counts['area']);
  }

  #[Test]
  public function testResolvesFacilityNamesByIdsScopedToOrganization(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-stats-names');
    $otherOrganization = $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'facility-stats-names-other');

    $this->createFacilityNamed('550e8400-e29b-41d4-a716-4466554a4030', $organization, 'Alpha Site');
    $this->createFacilityNamed('550e8400-e29b-41d4-a716-4466554a4031', $otherOrganization, 'Foreign Site');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $names = $this->adapter->getFacilityNamesByIds(self::ORGANIZATION_ID, [
      '550e8400-e29b-41d4-a716-4466554a4030',
      '550e8400-e29b-41d4-a716-4466554a4031',
    ]);

    self::assertSame('Alpha Site', $names['550e8400-e29b-41d4-a716-4466554a4030'] ?? null);
    // Cross-organization identifier is not resolved.
    self::assertArrayNotHasKey('550e8400-e29b-41d4-a716-4466554a4031', $names);
  }

  private function createOrganization(string $id, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Facility Statistics Test';
    $organization->slug = $slug;
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-4466554a4900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-4466554a4900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createFacility(string $id, OrganizationRecord $organization, string $type, string $status): void
  {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->parentFacility = null;
    $facility->type = $type;
    $facility->name = 'Facility ' . $id;
    $facility->code = null;
    $facility->status = $status;
    $facility->address = null;
    $facility->metadata = [];
    $facility->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $facility->updatedAt = $facility->createdAt;
    $this->entityManager->persist($facility);
  }

  private function createFacilityNamed(string $id, OrganizationRecord $organization, string $name): void
  {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->parentFacility = null;
    $facility->type = 'site';
    $facility->name = $name;
    $facility->code = null;
    $facility->status = 'active';
    $facility->address = null;
    $facility->metadata = [];
    $facility->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $facility->updatedAt = $facility->createdAt;
    $this->entityManager->persist($facility);
  }

  private function removeOrganization(string $id): void
  {
    foreach ($this->entityManager->getRepository(FacilityRecord::class)->findBy(['organization' => $id]) as $record) {
      $this->entityManager->remove($record);
    }
    $organization = $this->entityManager->find(OrganizationRecord::class, $id);
    if ($organization instanceof OrganizationRecord) {
      $this->entityManager->remove($organization);
    }
    $this->entityManager->flush();
  }
}
