<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\DeleteAttachment;

use Equipment\Application\Port\Outbound\Internal\{AttachmentRepositoryPort, EquipmentRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\DeleteAttachment\{DeleteAttachmentCommand, DeleteAttachmentHandler, DeleteAttachmentResult};
use Equipment\Domain\Exception\{AttachmentNotFoundException, EquipmentNotFoundException};
use Equipment\Domain\Model\Attachment\EquipmentAttachment;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId, EquipmentOrganizationId, EquipmentType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\FileCleanupPort;
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(DeleteAttachmentHandler::class)]
final class DeleteAttachmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655447001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655447002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655447003';

  // #region Methods
  #[Test]
  public function testInvokeDeletesAttachment(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $attachment = $this->buildAttachment();

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var AttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(AttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);
    $attachmentRepository->expects(self::once())->method('delete');

    /** @var FileCleanupPort&MockObject $cleanup */
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::once())->method('request');

    $handler = $this->handler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      cleanup: $cleanup,
    );

    $result = $handler->__invoke(new DeleteAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));

    self::assertInstanceOf(DeleteAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
    self::assertSame(self::EQUIP_ID, $result->equipmentId);
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentNotFound(): void
  {
    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn(null);

    /** @var AttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(AttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('delete');

    /** @var FileCleanupPort&MockObject $cleanup */
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::never())->method('request');

    $handler = $this->handler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      cleanup: $cleanup,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new DeleteAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentNotFound(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var AttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(AttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn(null);
    $attachmentRepository->expects(self::never())->method('delete');

    /** @var FileCleanupPort&MockObject $cleanup */
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::never())->method('request');

    $handler = $this->handler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      cleanup: $cleanup,
    );

    $this->expectException(AttachmentNotFoundException::class);

    $handler->__invoke(new DeleteAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentBelongsToAnotherEquipment(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $otherEquipmentAttachment = EquipmentAttachment::create(
      id: AttachmentId::fromString(self::ATTACHMENT_ID),
      equipmentId: EquipmentId::fromString('550e8400-e29b-41d4-a716-446655447099'),
      fileName: 'report.pdf',
      storagePath: 'equipment/other/report.pdf',
      mimeType: 'application/pdf',
      size: 12345,
      label: null,
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var AttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(AttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($otherEquipmentAttachment);
    $attachmentRepository->expects(self::never())->method('delete');

    /** @var FileCleanupPort&MockObject $cleanup */
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::never())->method('request');

    $handler = $this->handler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      cleanup: $cleanup,
    );

    $this->expectException(AttachmentNotFoundException::class);

    $handler->__invoke(new DeleteAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentForMalformedIdentifiers(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::never())->method('findById');

    /** @var FileCleanupPort&MockObject $cleanup */
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::never())->method('request');

    $handler = $this->handler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $this->createStub(AttachmentRepositoryPort::class),
      cleanup: $cleanup,
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new DeleteAttachmentCommand(
      organizationId: 'not-a-uuid',
      equipmentId: 'also-not-a-uuid',
      attachmentId: 'still-not-a-uuid',
    ));
  }

  private function buildAttachment(): EquipmentAttachment
  {
    return EquipmentAttachment::create(
      id: AttachmentId::fromString(self::ATTACHMENT_ID),
      equipmentId: EquipmentId::fromString(self::EQUIP_ID),
      fileName: 'report.pdf',
      storagePath: 'equipment/' . self::EQUIP_ID . '/attachments/' . self::ATTACHMENT_ID . '_report.pdf',
      mimeType: 'application/pdf',
      size: 12345,
      label: null,
    );
  }

  // #endregion
  private function handler(EquipmentRepositoryPort $equipmentRepository, AttachmentRepositoryPort $attachmentRepository, FileCleanupPort $cleanup): DeleteAttachmentHandler
  {
    $transactions = $this->createStub(\Shared\Application\Port\Outbound\RootTransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());
    $interventions = $this->createStub(\Intervention\Application\Port\Inbound\InterventionResourcesPort::class);

    return new DeleteAttachmentHandler(
      $equipmentRepository,
      $attachmentRepository,
      $transactions,
      $this->createStub(\Equipment\Application\Port\Outbound\EquipmentMediaLockPort::class),
      $this->createStub(\Equipment\Application\Port\Outbound\EquipmentMediaReadPort::class),
      new \Equipment\Application\Service\EquipmentMediaAccess($this->createStub(\Organization\Application\Port\Inbound\OrganizationAuthorizationPort::class), $interventions),
      $interventions,
      $cleanup,
    );
  }
}
