<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Adapter\Inspection;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Adapter\Inspection\FacilityNamingAdapter;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test FacilityNamingAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityNamingAdapter::class)]
final class FacilityNamingAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554a1000';

  private const string FACILITY_ONE = '550e8400-e29b-41d4-a716-4466554a1010';

  private const string FACILITY_TWO = '550e8400-e29b-41d4-a716-4466554a1011';

  private const string UNKNOWN_ID = '550e8400-e29b-41d4-a716-4466554a1099';

  private EntityManagerInterface $entityManager;

  private FacilityNamingAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->adapter = new FacilityNamingAdapter($this->entityManager);

    $this->removeOrganization(self::ORGANIZATION_ID);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testResolvesKnownNamesAndOmitsUnknownIds(): void
  {
    $organization = $this->createOrganization();
    $this->createFacility(self::FACILITY_ONE, $organization, 'Head Office');
    $this->createFacility(self::FACILITY_TWO, $organization, 'Warehouse');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $names = $this->adapter->findNamesByIds([
      self::FACILITY_ONE,
      self::FACILITY_TWO,
      self::UNKNOWN_ID,
    ]);

    self::assertSame('Head Office', $names[self::FACILITY_ONE] ?? null);
    self::assertSame('Warehouse', $names[self::FACILITY_TWO] ?? null);
    // An identifier we cannot resolve is absent, never a blank name.
    self::assertArrayNotHasKey(self::UNKNOWN_ID, $names);
  }

  #[Test]
  public function testReturnsEmptyArrayForNoIds(): void
  {
    self::assertSame([], $this->adapter->findNamesByIds([]));
  }

  private function createOrganization(): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Facility Naming Test';
    $organization->slug = 'facility-inspection-naming-test';
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-4466554a1900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-4466554a1900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createFacility(string $id, OrganizationRecord $organization, string $name): void
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
