<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Domain\Model\Attachment\EquipmentAttachment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId};
use Equipment\Infrastructure\Persistence\Doctrine\Mapper\AttachmentMapper;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentAttachmentRecord, EquipmentRecord};
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test AttachmentMapperTest.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AttachmentMapper::class)]
final class AttachmentMapperTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-4466554b0001';

  private const string EQUIPMENT_ID = '660e8400-e29b-41d4-a716-4466554b0002';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->createOrganization();
    $this->createEquipment();
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
  public function testToDomainReconstitutesAttachmentFromPersistedRecord(): void
  {
    $this->persistAttachmentRecord(
      id: '660e8400-e29b-41d4-a716-4466554b0010',
      fileName: 'manual.pdf',
      storagePath: 'org/equipment/manual-b0010.pdf',
      mimeType: 'application/pdf',
      size: 20480,
      uploadedAt: new DateTimeImmutable('2026-05-01T09:00:00+00:00'),
      label: 'User manual',
    );
    $this->entityManager->clear();

    $record = $this->entityManager->find(
      EquipmentAttachmentRecord::class,
      '660e8400-e29b-41d4-a716-4466554b0010',
    );
    self::assertInstanceOf(EquipmentAttachmentRecord::class, $record);

    $attachment = AttachmentMapper::toDomain($record);

    self::assertSame('660e8400-e29b-41d4-a716-4466554b0010', (string) $attachment->id());
    self::assertSame(self::EQUIPMENT_ID, (string) $attachment->equipmentId());
    self::assertSame('manual.pdf', $attachment->fileName());
    self::assertSame('org/equipment/manual-b0010.pdf', $attachment->storagePath());
    self::assertSame('application/pdf', $attachment->mimeType());
    self::assertSame(20480, $attachment->size());
    self::assertSame('User manual', $attachment->label());
    self::assertEquals(new DateTimeImmutable('2026-05-01T09:00:00+00:00'), $attachment->uploadedAt());
  }

  #[Test]
  public function testToDomainKeepsNullLabelFromPersistedRecord(): void
  {
    $this->persistAttachmentRecord(
      id: '660e8400-e29b-41d4-a716-4466554b0011',
      fileName: 'photo.jpg',
      storagePath: 'org/equipment/photo-b0011.jpg',
      mimeType: 'image/jpeg',
      size: 4096,
      uploadedAt: new DateTimeImmutable('2026-05-02T09:00:00+00:00'),
      label: null,
    );
    $this->entityManager->clear();

    $record = $this->entityManager->find(
      EquipmentAttachmentRecord::class,
      '660e8400-e29b-41d4-a716-4466554b0011',
    );
    self::assertInstanceOf(EquipmentAttachmentRecord::class, $record);

    $attachment = AttachmentMapper::toDomain($record);

    self::assertNull($attachment->label());
    self::assertSame('photo.jpg', $attachment->fileName());
  }

  #[Test]
  public function testToDomainThrowsWhenRecordHasNoEquipment(): void
  {
    $record = new EquipmentAttachmentRecord();
    $record->id = '660e8400-e29b-41d4-a716-4466554b00ff';
    $record->fileName = 'orphan.pdf';
    $record->storagePath = 'org/equipment/orphan-b00ff.pdf';
    $record->mimeType = 'application/pdf';
    $record->size = 128;
    $record->uploadedAt = new DateTimeImmutable('2026-05-03T09:00:00+00:00');

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Attachment record must reference equipment.');

    AttachmentMapper::toDomain($record);
  }

  #[Test]
  public function testToRecordMapsAllFieldsAndIsPersistable(): void
  {
    $attachment = EquipmentAttachment::reconstitute(
      id: AttachmentId::fromString('660e8400-e29b-41d4-a716-4466554b0020'),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      fileName: 'report.pdf',
      storagePath: 'org/equipment/report-b0020.pdf',
      mimeType: 'application/pdf',
      size: 8192,
      uploadedAt: new DateTimeImmutable('2026-06-01T09:00:00+00:00'),
      label: 'Inspection report',
    );

    $record = AttachmentMapper::toRecord($attachment);

    self::assertSame('660e8400-e29b-41d4-a716-4466554b0020', $record->id);
    self::assertSame('report.pdf', $record->fileName);
    self::assertSame('org/equipment/report-b0020.pdf', $record->storagePath);
    self::assertSame('application/pdf', $record->mimeType);
    self::assertSame(8192, $record->size);
    self::assertSame('Inspection report', $record->label);
    self::assertEquals(new DateTimeImmutable('2026-06-01T09:00:00+00:00'), $record->uploadedAt);
    self::assertNull($record->equipment);

    $record->equipment = $this->entityManager->getReference(EquipmentRecord::class, self::EQUIPMENT_ID);
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $reloaded = $this->entityManager->find(
      EquipmentAttachmentRecord::class,
      '660e8400-e29b-41d4-a716-4466554b0020',
    );
    self::assertInstanceOf(EquipmentAttachmentRecord::class, $reloaded);
    self::assertSame('report.pdf', $reloaded->fileName);
    self::assertSame('Inspection report', $reloaded->label);
    self::assertInstanceOf(EquipmentRecord::class, $reloaded->equipment);
    self::assertSame(self::EQUIPMENT_ID, $reloaded->equipment->id);
  }

  #[Test]
  public function testToRecordKeepsNullLabel(): void
  {
    $attachment = EquipmentAttachment::reconstitute(
      id: AttachmentId::fromString('660e8400-e29b-41d4-a716-4466554b0021'),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      fileName: 'plan.dwg',
      storagePath: 'org/equipment/plan-b0021.dwg',
      mimeType: 'application/acad',
      size: 65536,
      uploadedAt: new DateTimeImmutable('2026-06-02T09:00:00+00:00'),
      label: null,
    );

    $record = AttachmentMapper::toRecord($attachment);

    self::assertNull($record->label);
    self::assertSame('plan.dwg', $record->fileName);
    self::assertSame(65536, $record->size);
  }

  private function persistAttachmentRecord(
    string $id,
    string $fileName,
    string $storagePath,
    string $mimeType,
    int $size,
    DateTimeImmutable $uploadedAt,
    ?string $label,
  ): void {
    $record = new EquipmentAttachmentRecord();
    $record->id = $id;
    $record->equipment = $this->entityManager->getReference(EquipmentRecord::class, self::EQUIPMENT_ID);
    $record->fileName = $fileName;
    $record->storagePath = $storagePath;
    $record->mimeType = $mimeType;
    $record->size = $size;
    $record->uploadedAt = $uploadedAt;
    $record->label = $label;
    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Attachment Mapper Test';
    $organization->slug = 'attachment-mapper-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-4466554b9000';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-4466554b9000';
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
