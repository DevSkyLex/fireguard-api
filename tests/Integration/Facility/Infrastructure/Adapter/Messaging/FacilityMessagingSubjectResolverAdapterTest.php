<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Adapter\Messaging;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Adapter\Messaging\FacilityMessagingSubjectResolverAdapter;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test FacilityMessagingSubjectResolverAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityMessagingSubjectResolverAdapter::class)]
final class FacilityMessagingSubjectResolverAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554a2000';

  private const string OTHER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554a2001';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-4466554a2010';

  private const string DRAFT_FACILITY_ID = '550e8400-e29b-41d4-a716-4466554a2011';

  private const string UNKNOWN_ID = '550e8400-e29b-41d4-a716-4466554a2099';

  private EntityManagerInterface $entityManager;

  private FacilityMessagingSubjectResolverAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->adapter = new FacilityMessagingSubjectResolverAdapter($this->entityManager);

    $this->removeOrganization(self::ORGANIZATION_ID);
    $this->removeOrganization(self::OTHER_ORGANIZATION_ID);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSupportsOnlyFacilitySubjects(): void
  {
    self::assertTrue($this->adapter->supports(MessagingSubjectType::FACILITY));
    self::assertFalse($this->adapter->supports(MessagingSubjectType::EQUIPMENT));
  }

  #[Test]
  public function testResolvesPublishedFacilityInTheOwningOrganization(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-messaging-owner');
    $this->createFacility(self::FACILITY_ID, $organization, 'Main Depot', 'published');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $resolution = $this->adapter->resolve(self::ORGANIZATION_ID, self::FACILITY_ID);

    self::assertTrue($resolution->exists);
    self::assertSame('Main Depot', $resolution->label);
    self::assertSame('organization.facilities.read', $resolution->requiredReadPermission);
  }

  #[Test]
  public function testDoesNotResolveUnknownFacility(): void
  {
    $this->createOrganization(self::ORGANIZATION_ID, 'facility-messaging-unknown');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $resolution = $this->adapter->resolve(self::ORGANIZATION_ID, self::UNKNOWN_ID);

    self::assertFalse($resolution->exists);
    self::assertNull($resolution->label);
  }

  #[Test]
  public function testDoesNotResolveFacilityFromAnotherOrganization(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-messaging-tenant');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'facility-messaging-other');
    $this->createFacility(self::FACILITY_ID, $organization, 'Cross Tenant', 'published');

    $this->entityManager->flush();
    $this->entityManager->clear();

    // Tenant isolation: the record exists, but not for the requesting organization.
    $resolution = $this->adapter->resolve(self::OTHER_ORGANIZATION_ID, self::FACILITY_ID);

    self::assertFalse($resolution->exists);
    self::assertNull($resolution->label);
  }

  #[Test]
  public function testDoesNotResolveDraftFacility(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-messaging-draft');
    $this->createFacility(self::DRAFT_FACILITY_ID, $organization, 'Draft Scratchpad', 'draft');

    $this->entityManager->flush();
    $this->entityManager->clear();

    // A draft (intervention scratchpad) facility is not an addressable subject.
    $resolution = $this->adapter->resolve(self::ORGANIZATION_ID, self::DRAFT_FACILITY_ID);

    self::assertFalse($resolution->exists);
    self::assertNull($resolution->label);
  }

  private function createOrganization(string $id, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Facility Messaging Test';
    $organization->slug = $slug;
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-4466554a2900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-4466554a2900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createFacility(string $id, OrganizationRecord $organization, string $name, string $recordStatus): void
  {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->parentFacility = null;
    $facility->type = 'site';
    $facility->name = $name;
    $facility->code = null;
    $facility->status = 'active';
    $facility->recordStatus = $recordStatus;
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
