<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Adapter\Intervention;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Inbound\FacilityArchivalGuardPort;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\Port\Outbound\FacilityMetadataFieldRepositoryPort;
use Facility\Application\Service\FacilityMetadataSchemaGuard;
use Facility\Infrastructure\Adapter\Intervention\FacilityInterventionResourceAdapter;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Domain\Exception\InterventionResourceNotFoundException;
use Intervention\Domain\ValueObject\InterventionResourceType;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test FacilityInterventionResourceAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityInterventionResourceAdapter::class)]
final class FacilityInterventionResourceAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655440000';

  private const string OTHER_ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655440001';

  private EntityManagerInterface $entityManager;

  private FacilityInterventionResourceAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    // The archival guard is only consulted by apply()'s archiving path, which
    // these tests do not exercise; a stub keeps the adapter constructible.
    $guard = self::createStub(FacilityArchivalGuardPort::class);
    /** @var FacilityRepositoryPort $facilityRepository */
    $facilityRepository = static::getContainer()->get(FacilityRepositoryPort::class);
    $metadataRepository = self::createStub(FacilityMetadataFieldRepositoryPort::class);
    $metadataRepository->method('findByOrganizationId')->willReturn([]);
    $this->adapter = new FacilityInterventionResourceAdapter($this->entityManager, $guard, $facilityRepository, new FacilityMetadataSchemaGuard($metadataRepository));

    $this->removeOrganization(self::ORGANIZATION_ID);
    $this->removeOrganization(self::OTHER_ORGANIZATION_ID);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSupportsMatchesFacilityResourceIris(): void
  {
    self::assertTrue($this->adapter->supports('/api/facilities/770e8400-e29b-41d4-a716-446655440010'));
    self::assertFalse($this->adapter->supports('/api/equipment/770e8400-e29b-41d4-a716-446655440010'));
    self::assertFalse($this->adapter->supports('/api/facilities/770e8400-e29b-41d4-a716-446655440010/children'));
  }

  #[Test]
  public function testSupportsResourceTypeOnlyAcceptsFacility(): void
  {
    self::assertTrue($this->adapter->supportsResourceType(InterventionResourceType::FACILITY));
    self::assertFalse($this->adapter->supportsResourceType(InterventionResourceType::EQUIPMENT));
    self::assertFalse($this->adapter->supportsResourceType(InterventionResourceType::INSPECTION));
  }

  #[Test]
  public function testResourceExistsReflectsPersistedRecords(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-intervention-exists');
    $this->createFacility('770e8400-e29b-41d4-a716-446655440020', $organization);
    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertTrue($this->adapter->resourceExists('770e8400-e29b-41d4-a716-446655440020'));
    self::assertFalse($this->adapter->resourceExists('770e8400-e29b-41d4-a716-446655440099'));
  }

  #[Test]
  public function testResourceBelongsToOrganizationEnforcesTenantIsolation(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-intervention-belongs');
    $this->createFacility('770e8400-e29b-41d4-a716-446655440030', $organization);
    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertTrue($this->adapter->resourceBelongsToOrganization(
      '770e8400-e29b-41d4-a716-446655440030',
      self::ORGANIZATION_ID,
    ));
    // A different organization never owns the record.
    self::assertFalse($this->adapter->resourceBelongsToOrganization(
      '770e8400-e29b-41d4-a716-446655440030',
      self::OTHER_ORGANIZATION_ID,
    ));
    // A missing record belongs to no one.
    self::assertFalse($this->adapter->resourceBelongsToOrganization(
      '770e8400-e29b-41d4-a716-446655440099',
      self::ORGANIZATION_ID,
    ));
  }

  #[Test]
  public function testClientIdExistsFindsAssignedClientIdentifier(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-intervention-client');
    $facility = $this->createFacility('770e8400-e29b-41d4-a716-446655440040', $organization);
    $facility->clientId = '770e8400-e29b-41d4-a716-446655440041';
    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertTrue($this->adapter->clientIdExists('770e8400-e29b-41d4-a716-446655440041'));
    self::assertFalse($this->adapter->clientIdExists('770e8400-e29b-41d4-a716-446655440099'));
  }

  #[Test]
  public function testAssignToInterventionMarksRecordAsDraft(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-intervention-assign-draft');
    $this->createFacility('770e8400-e29b-41d4-a716-446655440050', $organization);
    $this->entityManager->flush();

    $assignment = $this->adapter->assign(
      '770e8400-e29b-41d4-a716-446655440050',
      '770e8400-e29b-41d4-a716-446655440051',
      '770e8400-e29b-41d4-a716-446655440052',
    );

    self::assertSame('770e8400-e29b-41d4-a716-446655440051', $assignment->interventionId);
    self::assertSame('draft', $assignment->recordStatus);
    self::assertSame(1, $assignment->revision);

    $this->entityManager->clear();
    $reloaded = $this->entityManager->find(FacilityRecord::class, '770e8400-e29b-41d4-a716-446655440050');
    self::assertInstanceOf(FacilityRecord::class, $reloaded);
    self::assertSame('draft', $reloaded->recordStatus);
    self::assertSame('770e8400-e29b-41d4-a716-446655440051', $reloaded->interventionId);
    self::assertSame('770e8400-e29b-41d4-a716-446655440052', $reloaded->clientId);
  }

  #[Test]
  public function testAssignWithoutInterventionPublishesRecord(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-intervention-assign-pub');
    $this->createFacility('770e8400-e29b-41d4-a716-446655440060', $organization);
    $this->entityManager->flush();

    $assignment = $this->adapter->assign('770e8400-e29b-41d4-a716-446655440060', null, null);

    self::assertNull($assignment->interventionId);
    self::assertSame('published', $assignment->recordStatus);
    self::assertSame(1, $assignment->revision);
  }

  #[Test]
  public function testAssignThrowsWhenRecordIsMissing(): void
  {
    $this->expectException(InterventionResourceNotFoundException::class);

    $this->adapter->assign('770e8400-e29b-41d4-a716-446655440099', null, null);
  }

  #[Test]
  public function testCountForInterventionAndCountsForInterventions(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-intervention-counts');
    $interventionA = '770e8400-e29b-41d4-a716-446655440071';
    $interventionB = '770e8400-e29b-41d4-a716-446655440072';

    $this->createFacilityForIntervention('770e8400-e29b-41d4-a716-446655440073', $organization, $interventionA);
    $this->createFacilityForIntervention('770e8400-e29b-41d4-a716-446655440074', $organization, $interventionA);
    $this->createFacilityForIntervention('770e8400-e29b-41d4-a716-446655440075', $organization, $interventionB);
    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertSame(2, $this->adapter->countForIntervention($interventionA));
    self::assertSame(1, $this->adapter->countForIntervention($interventionB));

    $counts = $this->adapter->countsForInterventions([$interventionA, $interventionB]);
    self::assertSame(2, $counts[$interventionA] ?? null);
    self::assertSame(1, $counts[$interventionB] ?? null);

    // Empty input short-circuits without a query.
    self::assertSame([], $this->adapter->countsForInterventions([]));
  }

  #[Test]
  public function testBlockerCountsForInterventionsIsAlwaysEmpty(): void
  {
    self::assertSame([], $this->adapter->blockerCountsForInterventions([
      '770e8400-e29b-41d4-a716-446655440080',
    ]));
  }

  #[Test]
  public function testPublishDraftsFlipsDraftRecordsToPublished(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-intervention-publish');
    $interventionId = '770e8400-e29b-41d4-a716-446655440091';
    $draft = $this->createFacilityForIntervention('770e8400-e29b-41d4-a716-446655440092', $organization, $interventionId);
    $draft->recordStatus = 'draft';
    $draft->revision = 1;
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->adapter->publishDrafts($interventionId);
    $this->entityManager->clear();

    $reloaded = $this->entityManager->find(FacilityRecord::class, '770e8400-e29b-41d4-a716-446655440092');
    self::assertInstanceOf(FacilityRecord::class, $reloaded);
    self::assertSame('published', $reloaded->recordStatus);
    self::assertSame(2, $reloaded->revision);
  }

  #[Test]
  public function testDiscardDraftsDeletesDraftsButKeepsPublished(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-intervention-discard');
    $interventionId = '770e8400-e29b-41d4-a716-446655440101';

    $draft = $this->createFacilityForIntervention('770e8400-e29b-41d4-a716-446655440102', $organization, $interventionId);
    $draft->recordStatus = 'draft';
    $published = $this->createFacilityForIntervention('770e8400-e29b-41d4-a716-446655440103', $organization, $interventionId);
    $published->recordStatus = 'published';
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->adapter->discardDrafts($interventionId);
    $this->entityManager->clear();

    self::assertNull($this->entityManager->find(FacilityRecord::class, '770e8400-e29b-41d4-a716-446655440102'));
    self::assertInstanceOf(
      FacilityRecord::class,
      $this->entityManager->find(FacilityRecord::class, '770e8400-e29b-41d4-a716-446655440103'),
    );
  }

  private function createOrganization(string $id, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Facility Intervention Test';
    $organization->slug = $slug;
    $organization->ownerUserId = '770e8400-e29b-41d4-a716-446655449000';
    $organization->createdByUserId = '770e8400-e29b-41d4-a716-446655449000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createFacility(string $id, OrganizationRecord $organization): FacilityRecord
  {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->parentFacility = null;
    $facility->type = 'site';
    $facility->name = 'Facility ' . $id;
    $facility->code = null;
    $facility->status = 'active';
    $facility->address = null;
    $facility->metadata = [];
    $facility->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $facility->updatedAt = $facility->createdAt;
    $this->entityManager->persist($facility);

    return $facility;
  }

  private function createFacilityForIntervention(string $id, OrganizationRecord $organization, string $interventionId): FacilityRecord
  {
    $facility = $this->createFacility($id, $organization);
    $facility->interventionId = $interventionId;

    return $facility;
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
