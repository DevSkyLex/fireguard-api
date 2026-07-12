<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Inspection\CreateInspection;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{ChecklistValidationPort, EquipmentValidationPort, FacilityValidationPort, InspectionRepositoryPort};
use Inspection\Application\UseCase\Command\Inspection\CreateInspection\{
  CreateInspectionCommand,
  CreateInspectionHandler,
  CreateInspectionResult
};
use Inspection\Domain\ValueObject\{InspectionId, InspectionStatus};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test CreateInspectionHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateInspectionHandler::class)]
final class CreateInspectionHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  // #region Methods
  #[Test]
  public function testInvokePersistsInspectionAndReturnsResult(): void
  {
    $inspectionId = InspectionId::fromString(self::INSP_ID);

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(InspectionId::class)
      ->willReturn($inspectionId);

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save');

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = new CreateInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $equipmentValidation,
      facilityValidation: $facilityValidation,
      checklistValidation: $checklistValidation,
      uuidFactory: $uuidFactory,
    );

    $command = new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
    );

    $result = $handler->__invoke($command);

    self::assertInstanceOf(CreateInspectionResult::class, $result);
    self::assertSame(self::INSP_ID, $result->inspectionId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertSame(self::EQUIP_ID, $result->equipmentId);
    self::assertSame('pass', $result->result);
    self::assertSame(InspectionStatus::DRAFT->value, $result->status);
    self::assertSame('user', $result->inspectorType);
    self::assertSame('John Doe', $result->inspectorName);
    self::assertSame('user-abc', $result->inspectorUserId);
    self::assertNull($result->facilityId);
    self::assertNull($result->notes);
    self::assertInstanceOf(DateTimeImmutable::class, $result->createdAt);
  }

  #[Test]
  public function testInvokeWithExternalInspector(): void
  {
    $inspectionId = InspectionId::fromString(self::INSP_ID);

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn($inspectionId);

    $repository = $this->createStub(InspectionRepositoryPort::class);

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = new CreateInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $equipmentValidation,
      facilityValidation: $facilityValidation,
      checklistValidation: $checklistValidation,
      uuidFactory: $uuidFactory,
    );

    $command = new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'fail',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'external',
      inspectorName: 'Jane Smith',
      inspectorOrganizationName: 'Safety Corp',
    );

    $result = $handler->__invoke($command);

    self::assertSame('external', $result->inspectorType);
    self::assertSame('Jane Smith', $result->inspectorName);
    self::assertNull($result->inspectorUserId);
    self::assertSame('Safety Corp', $result->inspectorOrganizationName);
  }

  #[Test]
  public function testInvokeThrowsOnInvalidOrganizationUuid(): void
  {
    $uuidFactory = $this->createStub(UuidFactory::class);

    $repository = $this->createStub(InspectionRepositoryPort::class);

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = new CreateInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $equipmentValidation,
      facilityValidation: $facilityValidation,
      checklistValidation: $checklistValidation,
      uuidFactory: $uuidFactory,
    );

    $command = new CreateInspectionCommand(
      organizationId: 'not-a-uuid',
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke($command);
  }

  #[Test]
  public function testInvokeThrowsWhenUserInspectorWithoutUserId(): void
  {
    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(InspectionId::fromString(self::INSP_ID));

    $repository = $this->createStub(InspectionRepositoryPort::class);

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = new CreateInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $equipmentValidation,
      facilityValidation: $facilityValidation,
      checklistValidation: $checklistValidation,
      uuidFactory: $uuidFactory,
    );

    $command = new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      // inspectorUserId is null
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke($command);
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentNotFound(): void
  {
    $uuidFactory = $this->createStub(UuidFactory::class);

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    /** @var EquipmentValidationPort&MockObject $equipmentValidation */
    $equipmentValidation = $this->createMock(EquipmentValidationPort::class);
    $equipmentValidation->expects(self::once())
      ->method('assertEquipmentIsInspectable')
      ->willThrowException(new InvalidArgumentException('Equipment with ID "' . self::EQUIP_ID . '" not found.'));

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = new CreateInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $equipmentValidation,
      facilityValidation: $facilityValidation,
      checklistValidation: $checklistValidation,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
    ));
  }

  #[Test]
  public function testInvokeNormalizesInvalidEquipmentValidationValue(): void
  {
    $uuidFactory = $this->createStub(UuidFactory::class);

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    /** @var EquipmentValidationPort&MockObject $equipmentValidation */
    $equipmentValidation = $this->createMock(EquipmentValidationPort::class);
    $equipmentValidation->expects(self::once())
      ->method('assertEquipmentIsInspectable')
      ->willThrowException(InvalidValueException::because('Invalid UUID provided.'));

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = new CreateInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $equipmentValidation,
      facilityValidation: $facilityValidation,
      checklistValidation: $checklistValidation,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid UUID provided.');

    $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: 'not-a-uuid',
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityNotFound(): void
  {
    $uuidFactory = $this->createStub(UuidFactory::class);

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    /** @var FacilityValidationPort&MockObject $facilityValidation */
    $facilityValidation = $this->createMock(FacilityValidationPort::class);
    $facilityValidation->expects(self::once())
      ->method('assertFacilityIsUsable')
      ->willThrowException(new InvalidArgumentException('Facility with ID "550e8400-e29b-41d4-a716-446655440010" not found.'));

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = new CreateInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $equipmentValidation,
      facilityValidation: $facilityValidation,
      checklistValidation: $checklistValidation,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
      facilityId: '550e8400-e29b-41d4-a716-446655440010',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityIsArchived(): void
  {
    $uuidFactory = $this->createStub(UuidFactory::class);

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    /** @var FacilityValidationPort&MockObject $facilityValidation */
    $facilityValidation = $this->createMock(FacilityValidationPort::class);
    $facilityValidation->expects(self::once())
      ->method('assertFacilityIsUsable')
      ->willThrowException(new InvalidArgumentException('Facility with ID "550e8400-e29b-41d4-a716-446655440010" is archived and cannot be used.'));

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = new CreateInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $equipmentValidation,
      facilityValidation: $facilityValidation,
      checklistValidation: $checklistValidation,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
      facilityId: '550e8400-e29b-41d4-a716-446655440010',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenChecklistNotFound(): void
  {
    $uuidFactory = $this->createStub(UuidFactory::class);

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    /** @var ChecklistValidationPort&MockObject $checklistValidation */
    $checklistValidation = $this->createMock(ChecklistValidationPort::class);
    $checklistValidation->expects(self::once())
      ->method('assertChecklistIsUsable')
      ->willThrowException(new InvalidArgumentException('Checklist with ID "550e8400-e29b-41d4-a716-446655440020" not found.'));

    $handler = new CreateInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $equipmentValidation,
      facilityValidation: $facilityValidation,
      checklistValidation: $checklistValidation,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
      checklistId: '550e8400-e29b-41d4-a716-446655440020',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenChecklistIsArchived(): void
  {
    $uuidFactory = $this->createStub(UuidFactory::class);

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    /** @var ChecklistValidationPort&MockObject $checklistValidation */
    $checklistValidation = $this->createMock(ChecklistValidationPort::class);
    $checklistValidation->expects(self::once())
      ->method('assertChecklistIsUsable')
      ->willThrowException(new InvalidArgumentException('Checklist with ID "550e8400-e29b-41d4-a716-446655440020" is archived and cannot be used.'));

    $handler = new CreateInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $equipmentValidation,
      facilityValidation: $facilityValidation,
      checklistValidation: $checklistValidation,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
      checklistId: '550e8400-e29b-41d4-a716-446655440020',
    ));
  }
  // #endregion
}
