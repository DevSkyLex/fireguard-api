<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Adapter\Equipment;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Adapter\Equipment\FacilityNamingAdapter;
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
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655440000';

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
  public function testResolvesNamesKeyedByIdForKnownFacilities(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-naming-known');

    $this->createFacilityNamed('660e8400-e29b-41d4-a716-446655440010', $organization, 'Alpha Site');
    $this->createFacilityNamed('660e8400-e29b-41d4-a716-446655440011', $organization, 'Beta Building');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $names = $this->adapter->findNamesByIds([
      '660e8400-e29b-41d4-a716-446655440010',
      '660e8400-e29b-41d4-a716-446655440011',
    ]);

    self::assertSame('Alpha Site', $names['660e8400-e29b-41d4-a716-446655440010'] ?? null);
    self::assertSame('Beta Building', $names['660e8400-e29b-41d4-a716-446655440011'] ?? null);
  }

  #[Test]
  public function testUnknownIdentifiersAreAbsentFromResult(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-naming-unknown');

    $this->createFacilityNamed('660e8400-e29b-41d4-a716-446655440020', $organization, 'Known Site');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $names = $this->adapter->findNamesByIds([
      '660e8400-e29b-41d4-a716-446655440020',
      '660e8400-e29b-41d4-a716-446655440099',
    ]);

    self::assertSame('Known Site', $names['660e8400-e29b-41d4-a716-446655440020'] ?? null);
    // Unknown identifiers are omitted rather than mapped to an empty string.
    self::assertArrayNotHasKey('660e8400-e29b-41d4-a716-446655440099', $names);
  }

  #[Test]
  public function testEmptyInputReturnsEmptyArrayWithoutQuerying(): void
  {
    self::assertSame([], $this->adapter->findNamesByIds([]));
  }

  private function createOrganization(string $id, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Facility Naming Test';
    $organization->slug = $slug;
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655449000';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655449000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
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
