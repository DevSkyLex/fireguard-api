<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Domain\Model\Attachment\EquipmentAttachment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Infrastructure\Persistence\Doctrine\Repository\AttachmentRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test AttachmentRepositoryTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AttachmentRepository::class)]
final class AttachmentRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-4466554a0001';

  private const string EQUIPMENT_ID = '660e8400-e29b-41d4-a716-4466554a0002';

  private EntityManagerInterface $entityManager;

  private AttachmentRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new AttachmentRepository($this->entityManager);

    $this->createOrganization();
    $this->createEquipment();
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveAndFindByIdRoundTrip(): void
  {
    $id = AttachmentId::fromString('660e8400-e29b-41d4-a716-4466554a0010');
    $attachment = EquipmentAttachment::reconstitute(
      id: $id,
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      fileName: 'manual.pdf',
      storagePath: 'org/equipment/manual-a0010.pdf',
      mimeType: 'application/pdf',
      size: 20480,
      uploadedAt: new DateTimeImmutable('2026-05-01T09:00:00+00:00'),
      label: 'User manual',
    );

    $this->repository->save($attachment);
    $this->entityManager->clear();

    $found = $this->repository->findById($id);

    self::assertInstanceOf(EquipmentAttachment::class, $found);
    self::assertSame('manual.pdf', $found->fileName());
    self::assertSame('org/equipment/manual-a0010.pdf', $found->storagePath());
    self::assertSame('application/pdf', $found->mimeType());
    self::assertSame(20480, $found->size());
    self::assertSame('User manual', $found->label());
    self::assertSame(self::EQUIPMENT_ID, (string) $found->equipmentId());
  }

  #[Test]
  public function testSaveUpdatesTheExistingRecordInPlace(): void
  {
    $id = AttachmentId::fromString('660e8400-e29b-41d4-a716-4466554a0011');
    $this->repository->save(EquipmentAttachment::reconstitute(
      id: $id,
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      fileName: 'draft.pdf',
      storagePath: 'org/equipment/draft-a0011.pdf',
      mimeType: 'application/pdf',
      size: 100,
      uploadedAt: new DateTimeImmutable('2026-05-01T09:00:00+00:00'),
      label: null,
    ));
    $this->entityManager->clear();

    $this->repository->save(EquipmentAttachment::reconstitute(
      id: $id,
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      fileName: 'final.png',
      storagePath: 'org/equipment/final-a0011.png',
      mimeType: 'image/png',
      size: 4096,
      uploadedAt: new DateTimeImmutable('2026-05-03T10:30:00+00:00'),
      label: 'Final revision',
    ));
    $this->entityManager->clear();

    $found = $this->repository->findById($id);

    self::assertInstanceOf(EquipmentAttachment::class, $found);
    self::assertSame('final.png', $found->fileName());
    self::assertSame('org/equipment/final-a0011.png', $found->storagePath());
    self::assertSame('image/png', $found->mimeType());
    self::assertSame(4096, $found->size());
    self::assertSame('Final revision', $found->label());
    self::assertCount(1, $this->repository->findByEquipmentId(EquipmentId::fromString(self::EQUIPMENT_ID)));
  }

  #[Test]
  public function testFindByIdReturnsNullWhenMissing(): void
  {
    self::assertNull(
      $this->repository->findById(AttachmentId::fromString('660e8400-e29b-41d4-a716-4466554a00ff')),
    );
  }

  #[Test]
  public function testFindByEquipmentIdReturnsAttachmentsNewestFirst(): void
  {
    $older = EquipmentAttachment::reconstitute(
      id: AttachmentId::fromString('660e8400-e29b-41d4-a716-4466554a0021'),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      fileName: 'older.pdf',
      storagePath: 'org/equipment/older-a0021.pdf',
      mimeType: 'application/pdf',
      size: 1024,
      uploadedAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
      label: null,
    );
    $newer = EquipmentAttachment::reconstitute(
      id: AttachmentId::fromString('660e8400-e29b-41d4-a716-4466554a0022'),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      fileName: 'newer.pdf',
      storagePath: 'org/equipment/newer-a0022.pdf',
      mimeType: 'application/pdf',
      size: 2048,
      uploadedAt: new DateTimeImmutable('2026-06-01T09:00:00+00:00'),
      label: null,
    );

    $this->repository->save($older);
    $this->repository->save($newer);
    $this->entityManager->clear();

    $attachments = $this->repository->findByEquipmentId(EquipmentId::fromString(self::EQUIPMENT_ID));

    self::assertCount(2, $attachments);
    self::assertSame('newer.pdf', $attachments[0]->fileName());
    self::assertSame('older.pdf', $attachments[1]->fileName());
  }

  #[Test]
  public function testDeleteRemovesAttachment(): void
  {
    $id = AttachmentId::fromString('660e8400-e29b-41d4-a716-4466554a0030');
    $attachment = EquipmentAttachment::reconstitute(
      id: $id,
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      fileName: 'to-delete.pdf',
      storagePath: 'org/equipment/to-delete-a0030.pdf',
      mimeType: 'application/pdf',
      size: 512,
      uploadedAt: new DateTimeImmutable('2026-05-02T09:00:00+00:00'),
      label: null,
    );

    $this->repository->save($attachment);
    $this->entityManager->clear();
    self::assertInstanceOf(EquipmentAttachment::class, $this->repository->findById($id));

    $this->repository->delete($id);
    $this->entityManager->clear();

    self::assertNull($this->repository->findById($id));
  }

  #[Test]
  public function testDeleteMissingAttachmentIsNoOp(): void
  {
    $this->repository->delete(AttachmentId::fromString('660e8400-e29b-41d4-a716-4466554a0099'));

    self::assertTrue($this->entityManager->isOpen());
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Attachment Repository Test';
    $organization->slug = 'attachment-repository-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-4466554a9000';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-4466554a9000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createEquipment(): void
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;
    $equipment->organization = $organization;
    $equipment->type = 'fire_extinguisher';
    $equipment->status = 'operational';
    $equipment->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $equipment->updatedAt = $equipment->createdAt;
    $this->entityManager->persist($equipment);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM equipment_attachments WHERE equipment_id = :equipmentId',
      ['equipmentId' => self::EQUIPMENT_ID],
    );
    $connection->executeStatement(
      'DELETE FROM equipment WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
