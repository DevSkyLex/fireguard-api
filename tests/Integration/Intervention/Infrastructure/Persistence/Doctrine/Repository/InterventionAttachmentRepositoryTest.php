<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Domain\Model\Attachment\InterventionAttachment;
use Intervention\Domain\ValueObject\{InterventionAttachmentId, InterventionAttachmentKind};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionWorkItemRecord};
use Intervention\Infrastructure\Persistence\Doctrine\Repository\InterventionAttachmentRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test InterventionAttachmentRepository.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionAttachmentRepository::class)]
final class InterventionAttachmentRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655440001';

  private const string INTERVENTION_ID = '770e8400-e29b-41d4-a716-446655440002';

  private const string OTHER_INTERVENTION_ID = '770e8400-e29b-41d4-a716-446655440003';

  private const string ATTACHMENT_ID = '770e8400-e29b-41d4-a716-446655440010';

  private const string OLDER_ATTACHMENT_ID = '770e8400-e29b-41d4-a716-446655440011';

  private const string NEWER_ATTACHMENT_ID = '770e8400-e29b-41d4-a716-446655440012';

  private const string OTHER_ATTACHMENT_ID = '770e8400-e29b-41d4-a716-446655440013';

  private const string UNKNOWN_ATTACHMENT_ID = '770e8400-e29b-41d4-a716-4466554400ff';

  private const string WORK_ITEM_ID = '770e8400-e29b-41d4-a716-446655440020';

  private const string OTHER_WORK_ITEM_ID = '770e8400-e29b-41d4-a716-446655440021';

  private EntityManagerInterface $entityManager;

  private InterventionAttachmentRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new InterventionAttachmentRepository($this->entityManager);

    $this->createOrganization();
    $this->createIntervention(self::INTERVENTION_ID, 1);
    $this->createIntervention(self::OTHER_INTERVENTION_ID, 2);
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveThenFindByIdRoundTripsAllFields(): void
  {
    $attachment = InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.pdf',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/evidence.pdf',
      mimeType: 'application/pdf',
      size: 20_480,
      uploadedAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      label: 'Execution evidence',
    );
    $this->repository->save($attachment);
    $this->entityManager->clear();

    $found = $this->repository->findById(InterventionAttachmentId::fromString(self::ATTACHMENT_ID));

    self::assertInstanceOf(InterventionAttachment::class, $found);
    self::assertSame(self::ATTACHMENT_ID, (string) $found->id());
    self::assertSame(self::INTERVENTION_ID, $found->interventionId());
    self::assertSame('evidence.pdf', $found->fileName());
    self::assertSame('interventions/' . self::INTERVENTION_ID . '/evidence.pdf', $found->storagePath());
    self::assertSame('application/pdf', $found->mimeType());
    self::assertSame(20_480, $found->size());
    self::assertSame('Execution evidence', $found->label());
    self::assertEquals(new DateTimeImmutable('2026-03-01T09:00:00+00:00'), $found->uploadedAt());
  }

  #[Test]
  public function testFindByInterventionIdReturnsOnlyOwnAttachmentsOrderedByUploadedAtDesc(): void
  {
    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::OLDER_ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'older.jpg',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/older.jpg',
      mimeType: 'image/jpeg',
      size: 1_024,
      uploadedAt: new DateTimeImmutable('2026-03-01T08:00:00+00:00'),
    ));
    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::NEWER_ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'newer.jpg',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/newer.jpg',
      mimeType: 'image/jpeg',
      size: 2_048,
      uploadedAt: new DateTimeImmutable('2026-03-02T08:00:00+00:00'),
    ));
    // Belongs to a different intervention: must be excluded.
    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::OTHER_ATTACHMENT_ID),
      interventionId: self::OTHER_INTERVENTION_ID,
      fileName: 'other.jpg',
      storagePath: 'interventions/' . self::OTHER_INTERVENTION_ID . '/other.jpg',
      mimeType: 'image/jpeg',
      size: 512,
      uploadedAt: new DateTimeImmutable('2026-03-03T08:00:00+00:00'),
    ));
    $this->entityManager->clear();

    $attachments = $this->repository->findByInterventionId(self::INTERVENTION_ID);

    $ids = array_map(static fn (InterventionAttachment $attachment): string => (string) $attachment->id(), $attachments);
    self::assertSame([self::NEWER_ATTACHMENT_ID, self::OLDER_ATTACHMENT_ID], $ids);
  }

  #[Test]
  public function testSaveUpdatesExistingAttachmentInPlace(): void
  {
    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'original.pdf',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/original.pdf',
      mimeType: 'application/pdf',
      size: 100,
      uploadedAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      label: 'Draft',
    ));
    $this->entityManager->clear();

    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'renamed.pdf',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/renamed.pdf',
      mimeType: 'application/pdf',
      size: 200,
      uploadedAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      label: 'Final',
    ));
    $this->entityManager->clear();

    $all = $this->repository->findByInterventionId(self::INTERVENTION_ID);
    self::assertCount(1, $all);
    self::assertSame('renamed.pdf', $all[0]->fileName());
    self::assertSame(200, $all[0]->size());
    self::assertSame('Final', $all[0]->label());
  }

  #[Test]
  public function testDeleteRemovesAttachment(): void
  {
    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.pdf',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/evidence.pdf',
      mimeType: 'application/pdf',
      size: 100,
      uploadedAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
    ));
    $this->entityManager->clear();

    $this->repository->delete(InterventionAttachmentId::fromString(self::ATTACHMENT_ID));
    $this->entityManager->clear();

    self::assertNull($this->repository->findById(InterventionAttachmentId::fromString(self::ATTACHMENT_ID)));
  }

  #[Test]
  public function testFindByIdReturnsNullWhenUnknown(): void
  {
    self::assertNull($this->repository->findById(InterventionAttachmentId::fromString(self::UNKNOWN_ATTACHMENT_ID)));
  }

  #[Test]
  public function testCountByInterventionIdCountsOnlyOwnAttachments(): void
  {
    self::assertSame(0, $this->repository->countByInterventionId(self::INTERVENTION_ID));

    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::OLDER_ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'older.jpg',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/older.jpg',
      mimeType: 'image/jpeg',
      size: 1_024,
      uploadedAt: new DateTimeImmutable('2026-03-01T08:00:00+00:00'),
    ));
    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::NEWER_ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'newer.jpg',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/newer.jpg',
      mimeType: 'image/jpeg',
      size: 2_048,
      uploadedAt: new DateTimeImmutable('2026-03-02T08:00:00+00:00'),
    ));
    // Another intervention's attachment must not inflate the count, or one
    // busy intervention would cap every other one in the organization.
    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::OTHER_ATTACHMENT_ID),
      interventionId: self::OTHER_INTERVENTION_ID,
      fileName: 'other.jpg',
      storagePath: 'interventions/' . self::OTHER_INTERVENTION_ID . '/other.jpg',
      mimeType: 'image/jpeg',
      size: 512,
      uploadedAt: new DateTimeImmutable('2026-03-03T08:00:00+00:00'),
    ));
    $this->entityManager->clear();

    self::assertSame(2, $this->repository->countByInterventionId(self::INTERVENTION_ID));
    self::assertSame(1, $this->repository->countByInterventionId(self::OTHER_INTERVENTION_ID));
  }

  #[Test]
  public function testSaveThenFindByIdRoundTripsTheWorkItemId(): void
  {
    $this->createWorkItem(self::WORK_ITEM_ID, self::INTERVENTION_ID);
    $this->entityManager->flush();

    $attachment = InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/evidence.jpg',
      mimeType: 'image/jpeg',
      size: 1_024,
      uploadedAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      workItemId: self::WORK_ITEM_ID,
    );
    $this->repository->save($attachment);
    $this->entityManager->clear();

    $found = $this->repository->findById(InterventionAttachmentId::fromString(self::ATTACHMENT_ID));

    self::assertInstanceOf(InterventionAttachment::class, $found);
    self::assertSame(self::WORK_ITEM_ID, $found->workItemId());
  }

  #[Test]
  public function testFindByInterventionIdFiltersByWorkItemId(): void
  {
    $this->createWorkItem(self::WORK_ITEM_ID, self::INTERVENTION_ID);
    $this->createWorkItem(self::OTHER_WORK_ITEM_ID, self::INTERVENTION_ID);
    $this->entityManager->flush();

    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'scoped.jpg',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/scoped.jpg',
      mimeType: 'image/jpeg',
      size: 1_024,
      uploadedAt: new DateTimeImmutable('2026-03-01T08:00:00+00:00'),
      workItemId: self::WORK_ITEM_ID,
    ));
    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::OTHER_ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'other-work-item.jpg',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/other-work-item.jpg',
      mimeType: 'image/jpeg',
      size: 1_024,
      uploadedAt: new DateTimeImmutable('2026-03-01T08:30:00+00:00'),
      workItemId: self::OTHER_WORK_ITEM_ID,
    ));
    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::OLDER_ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'unscoped.jpg',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/unscoped.jpg',
      mimeType: 'image/jpeg',
      size: 1_024,
      uploadedAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
    ));
    $this->entityManager->clear();

    $scoped = $this->repository->findByInterventionId(self::INTERVENTION_ID, self::WORK_ITEM_ID);

    self::assertCount(1, $scoped);
    self::assertSame(self::ATTACHMENT_ID, (string) $scoped[0]->id());
  }

  #[Test]
  public function testDeletingTheWorkItemSetsTheAttachmentWorkItemToNullInsteadOfDeletingTheAttachment(): void
  {
    $this->createWorkItem(self::WORK_ITEM_ID, self::INTERVENTION_ID);
    $this->entityManager->flush();

    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/evidence.jpg',
      mimeType: 'image/jpeg',
      size: 1_024,
      uploadedAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      workItemId: self::WORK_ITEM_ID,
    ));
    $this->entityManager->clear();

    // Deleting the work item at the database level (mirroring a real
    // DELETE /intervention-work-items/{id}) must not cascade-delete the
    // attachment — the FK is ON DELETE SET NULL, so the attachment survives
    // as plain intervention-level evidence.
    $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM intervention_work_items WHERE id = :id',
      ['id' => self::WORK_ITEM_ID],
    );
    $this->entityManager->clear();

    $found = $this->repository->findById(InterventionAttachmentId::fromString(self::ATTACHMENT_ID));

    self::assertInstanceOf(InterventionAttachment::class, $found);
    self::assertNull($found->workItemId());
  }

  #[Test]
  public function testSaveThenFindByIdRoundTripsTheSignatureKind(): void
  {
    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'signature.png',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/signature.png',
      mimeType: 'image/png',
      size: 1_024,
      uploadedAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      kind: InterventionAttachmentKind::SIGNATURE,
    ));
    $this->entityManager->clear();

    $found = $this->repository->findById(InterventionAttachmentId::fromString(self::ATTACHMENT_ID));

    self::assertInstanceOf(InterventionAttachment::class, $found);
    self::assertSame(InterventionAttachmentKind::SIGNATURE, $found->kind());
  }

  #[Test]
  public function testFindByIdDefaultsAPersistedAttachmentToTheFileKind(): void
  {
    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/evidence.jpg',
      mimeType: 'image/jpeg',
      size: 1_024,
      uploadedAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
    ));
    $this->entityManager->clear();

    $found = $this->repository->findById(InterventionAttachmentId::fromString(self::ATTACHMENT_ID));

    self::assertInstanceOf(InterventionAttachment::class, $found);
    self::assertSame(InterventionAttachmentKind::FILE, $found->kind());
  }

  #[Test]
  public function testFindSignatureByInterventionIdReturnsNullWhenNoneExists(): void
  {
    self::assertNull($this->repository->findSignatureByInterventionId(self::INTERVENTION_ID));
    self::assertFalse($this->repository->hasSignature(self::INTERVENTION_ID));
  }

  #[Test]
  public function testFindSignatureByInterventionIdReturnsTheOnlySignatureAmongOtherFiles(): void
  {
    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::OLDER_ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/evidence.jpg',
      mimeType: 'image/jpeg',
      size: 1_024,
      uploadedAt: new DateTimeImmutable('2026-03-01T08:00:00+00:00'),
    ));
    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'signature.png',
      storagePath: 'interventions/' . self::INTERVENTION_ID . '/signature.png',
      mimeType: 'image/png',
      size: 512,
      uploadedAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      kind: InterventionAttachmentKind::SIGNATURE,
    ));
    // Another intervention's signature must not leak across scopes.
    $this->repository->save(InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::OTHER_ATTACHMENT_ID),
      interventionId: self::OTHER_INTERVENTION_ID,
      fileName: 'other-signature.png',
      storagePath: 'interventions/' . self::OTHER_INTERVENTION_ID . '/other-signature.png',
      mimeType: 'image/png',
      size: 512,
      uploadedAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      kind: InterventionAttachmentKind::SIGNATURE,
    ));
    $this->entityManager->clear();

    $signature = $this->repository->findSignatureByInterventionId(self::INTERVENTION_ID);

    self::assertInstanceOf(InterventionAttachment::class, $signature);
    self::assertSame(self::ATTACHMENT_ID, (string) $signature->id());
    self::assertTrue($this->repository->hasSignature(self::INTERVENTION_ID));
    self::assertTrue($this->repository->hasSignature(self::OTHER_INTERVENTION_ID));
  }

  private function createWorkItem(string $id, string $interventionId): void
  {
    $intervention = $this->entityManager->getReference(InterventionRecord::class, $interventionId);

    $record = new InterventionWorkItemRecord();
    $record->id = $id;
    $record->intervention = $intervention;
    $record->action = 'site_setup';
    $record->source = 'planned';
    $record->status = 'planned';
    $record->required = true;
    $record->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Attachment Repository Test';
    $organization->slug = 'attachment-repository-test';
    $organization->ownerUserId = '770e8400-e29b-41d4-a716-446655449000';
    $organization->createdByUserId = '770e8400-e29b-41d4-a716-446655449000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createIntervention(string $id, int $number): void
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $record = new InterventionRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->type = 'site_setup';
    $record->name = 'Intervention ' . $number;
    $record->number = $number;
    $record->status = 'draft';
    $record->priority = 'normal';
    $record->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM intervention_attachments WHERE intervention_id IN (SELECT id FROM interventions WHERE organization_id = :organizationId)',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM interventions WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
