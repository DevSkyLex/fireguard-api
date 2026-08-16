<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\SetEquipmentPlanPosition;

use DateTimeImmutable;
use Equipment\Application\Contract\FloorPlan\{
  FloorPlanAttachmentNotAncestorException,
  FloorPlanAttachmentNotFloorPlanException,
  FloorPlanAttachmentNotFoundException
};
use Equipment\Application\Port\Outbound\{EquipmentFloorPlanValidationPort, EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\SetEquipmentPlanPosition\{
  SetEquipmentPlanPositionCommand,
  SetEquipmentPlanPositionHandler,
  SetEquipmentPlanPositionResult
};
use Equipment\Domain\Exception\{
  EquipmentNotAssignedToFacilityException,
  EquipmentNotFoundException
};
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentFacilityId, EquipmentId, EquipmentOrganizationId, EquipmentType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test SetEquipmentPlanPositionHandlerTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SetEquipmentPlanPositionHandler::class)]
final class SetEquipmentPlanPositionHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655440004';

  #[Test]
  public function testInvokeSetsThePlanPosition(): void
  {
    $equipment = $this->assignedEquipment();

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())->method('findById')->willReturn($equipment);
    $equipmentRepository->expects(self::once())->method('save');

    /** @var EquipmentFloorPlanValidationPort&MockObject $floorPlanValidation */
    $floorPlanValidation = $this->createMock(EquipmentFloorPlanValidationPort::class);
    $floorPlanValidation->expects(self::once())
      ->method('assertAttachmentUsableForFacility')
      ->with(self::ATTACHMENT_ID, self::FACILITY_ID);

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    $handler = new SetEquipmentPlanPositionHandler(
      equipmentRepository: $equipmentRepository,
      floorPlanValidation: $floorPlanValidation,
      tagRepository: $tagRepository,
    );

    $result = $handler->__invoke(new SetEquipmentPlanPositionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
      x: 0.42,
      y: 0.17,
    ));

    self::assertInstanceOf(SetEquipmentPlanPositionResult::class, $result);
    self::assertSame(['attachmentId' => self::ATTACHMENT_ID, 'x' => 0.42, 'y' => 0.17], $result->planPosition);
  }

  #[Test]
  public function testInvokeClearsThePlanPositionWhenAllFieldsAreNull(): void
  {
    $equipment = $this->assignedEquipment();

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())->method('findById')->willReturn($equipment);
    $equipmentRepository->expects(self::once())->method('save');

    /** @var EquipmentFloorPlanValidationPort&MockObject $floorPlanValidation */
    $floorPlanValidation = $this->createMock(EquipmentFloorPlanValidationPort::class);
    $floorPlanValidation->expects(self::never())->method('assertAttachmentUsableForFacility');

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    $handler = new SetEquipmentPlanPositionHandler(
      equipmentRepository: $equipmentRepository,
      floorPlanValidation: $floorPlanValidation,
      tagRepository: $tagRepository,
    );

    $result = $handler->__invoke(new SetEquipmentPlanPositionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: null,
      x: null,
      y: null,
    ));

    self::assertNull($result->planPosition);
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentNotFound(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())->method('findById')->willReturn(null);
    $equipmentRepository->expects(self::never())->method('save');

    $handler = $this->handler($equipmentRepository, $this->createStub(EquipmentFloorPlanValidationPort::class));

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new SetEquipmentPlanPositionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
      x: 0.1,
      y: 0.1,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOnlyAttachmentIdIsProvided(): void
  {
    $equipment = $this->assignedEquipment();

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var EquipmentFloorPlanValidationPort&MockObject $floorPlanValidation */
    $floorPlanValidation = $this->createMock(EquipmentFloorPlanValidationPort::class);
    $floorPlanValidation->expects(self::never())->method('assertAttachmentUsableForFacility');

    $handler = $this->handler($equipmentRepository, $floorPlanValidation);

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new SetEquipmentPlanPositionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
      x: null,
      y: null,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentHasNoFacility(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var EquipmentFloorPlanValidationPort&MockObject $floorPlanValidation */
    $floorPlanValidation = $this->createMock(EquipmentFloorPlanValidationPort::class);
    $floorPlanValidation->expects(self::never())->method('assertAttachmentUsableForFacility');

    $handler = $this->handler($equipmentRepository, $floorPlanValidation);

    $this->expectException(EquipmentNotAssignedToFacilityException::class);

    $handler->__invoke(new SetEquipmentPlanPositionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
      x: 0.1,
      y: 0.1,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentIsUnknown(): void
  {
    $equipment = $this->assignedEquipment();

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var EquipmentFloorPlanValidationPort&MockObject $floorPlanValidation */
    $floorPlanValidation = $this->createMock(EquipmentFloorPlanValidationPort::class);
    $floorPlanValidation->expects(self::once())
      ->method('assertAttachmentUsableForFacility')
      ->willThrowException(FloorPlanAttachmentNotFoundException::withId(self::ATTACHMENT_ID));

    $handler = $this->handler($equipmentRepository, $floorPlanValidation);

    $this->expectException(FloorPlanAttachmentNotFoundException::class);

    $handler->__invoke(new SetEquipmentPlanPositionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
      x: 0.1,
      y: 0.1,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentIsNotAFloorPlan(): void
  {
    $equipment = $this->assignedEquipment();

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var EquipmentFloorPlanValidationPort&MockObject $floorPlanValidation */
    $floorPlanValidation = $this->createMock(EquipmentFloorPlanValidationPort::class);
    $floorPlanValidation->expects(self::once())
      ->method('assertAttachmentUsableForFacility')
      ->willThrowException(FloorPlanAttachmentNotFloorPlanException::forAttachment(self::ATTACHMENT_ID));

    $handler = $this->handler($equipmentRepository, $floorPlanValidation);

    $this->expectException(FloorPlanAttachmentNotFloorPlanException::class);

    $handler->__invoke(new SetEquipmentPlanPositionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
      x: 0.1,
      y: 0.1,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentIsNotAnAncestor(): void
  {
    $equipment = $this->assignedEquipment();

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var EquipmentFloorPlanValidationPort&MockObject $floorPlanValidation */
    $floorPlanValidation = $this->createMock(EquipmentFloorPlanValidationPort::class);
    $floorPlanValidation->expects(self::once())
      ->method('assertAttachmentUsableForFacility')
      ->willThrowException(FloorPlanAttachmentNotAncestorException::forAttachment(self::ATTACHMENT_ID, self::FACILITY_ID));

    $handler = $this->handler($equipmentRepository, $floorPlanValidation);

    $this->expectException(FloorPlanAttachmentNotAncestorException::class);

    $handler->__invoke(new SetEquipmentPlanPositionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
      x: 0.1,
      y: 0.1,
    ));
  }

  private function handler(
    EquipmentRepositoryPort $equipmentRepository,
    EquipmentFloorPlanValidationPort $floorPlanValidation,
  ): SetEquipmentPlanPositionHandler {
    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    return new SetEquipmentPlanPositionHandler(
      equipmentRepository: $equipmentRepository,
      floorPlanValidation: $floorPlanValidation,
      tagRepository: $tagRepository,
    );
  }

  private function assignedEquipment(): Equipment
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipment->assignToFacility(EquipmentFacilityId::fromString(self::FACILITY_ID), new DateTimeImmutable());

    return $equipment;
  }
}
