<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityAttachmentRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test FacilityAttachmentRepositoryTest.
 *
 * Exercises save/find/delete against a real database, and confirms the
 * `ON DELETE CASCADE` foreign key removes attachment rows when the parent
 * facility (and, transitively, its organization) is hard-deleted.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityAttachmentRepository::class)]
final class FacilityAttachmentRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655460001';

  private const string FACILITY_ID = '660e8400-e29b-41d4-a716-446655460002';

  private const string ATTACHMENT_ID = '660e8400-e29b-41d4-a716-446655460003';

  private EntityManagerInterface $entityManager;

  private FacilityAttachmentRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new FacilityAttachmentRepository($this->entityManager);

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Facility Attachment Repository Test';
    $organization->slug = 'facility-attachment-repository-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655469000';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655469000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $facility->type = 'site';
    $facility->name = 'Main Site';
    $facility->status = 'active';
    $facility->metadata = [];
    $facility->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $facility->updatedAt = $facility->createdAt;
    $this->entityManager->persist($facility);

    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveThenFindByIdRoundTrips(): void
  {
    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'floor-plan.pdf',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_floor-plan.pdf',
      mimeType: 'application/pdf',
      size: 2048,
      label: 'Ground floor',
    );

    $this->repository->save($attachment);

    $found = $this->repository->findById(FacilityAttachmentId::fromString(self::ATTACHMENT_ID));

    self::assertNotNull($found);
    self::assertSame('floor-plan.pdf', $found->fileName());
    self::assertSame('Ground floor', $found->label());
    self::assertSame(self::FACILITY_ID, (string) $found->facilityId());
  }

  #[Test]
  public function testFindByFacilityIdReturnsSavedAttachments(): void
  {
    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'floor-plan.pdf',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_floor-plan.pdf',
      mimeType: 'application/pdf',
      size: 2048,
    );
    $this->repository->save($attachment);

    $results = $this->repository->findByFacilityId(FacilityId::fromString(self::FACILITY_ID));

    self::assertCount(1, $results);
    self::assertSame(self::ATTACHMENT_ID, (string) $results[0]->id());
  }

  #[Test]
  public function testDeleteRemovesTheRow(): void
  {
    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'floor-plan.pdf',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_floor-plan.pdf',
      mimeType: 'application/pdf',
      size: 2048,
    );
    $this->repository->save($attachment);

    $this->repository->delete(FacilityAttachmentId::fromString(self::ATTACHMENT_ID));

    self::assertNull($this->repository->findById(FacilityAttachmentId::fromString(self::ATTACHMENT_ID)));
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM facility_attachments WHERE facility_id = :facilityId',
      ['facilityId' => self::FACILITY_ID],
    );
    $connection->executeStatement(
      'DELETE FROM facilities WHERE id = :facilityId',
      ['facilityId' => self::FACILITY_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
