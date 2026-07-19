<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Inspection\EditInspection;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{ChecklistValidationPort, EquipmentValidationPort, FacilityValidationPort, InspectionRepositoryPort};
use Inspection\Application\UseCase\Command\Inspection\EditInspection\{
  EditInspectionCommand,
  EditInspectionHandler,
  EditInspectionResult
};
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  Inspector
};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditInspectionHandler::class)]
final class EditInspectionHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testInvokeEditsInspectionSavesAndReturnsResult(): void
  {
    $inspection = $this->makeDraftInspection();

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);
    $repository->expects(self::once())->method('save');

    $handler = new EditInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $this->createStub(EquipmentValidationPort::class),
      facilityValidation: $this->createStub(FacilityValidationPort::class),
      checklistValidation: $this->createStub(ChecklistValidationPort::class),
    );

    $result = $handler->__invoke(new EditInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      notes: 'Updated notes',
      hasNotes: true,
    ));

    self::assertInstanceOf(EditInspectionResult::class, $result);
    self::assertSame(self::INSP_ID, $result->inspectionId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertInstanceOf(DateTimeImmutable::class, $result->updatedAt);
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionNotFound(): void
  {
    $repository = $this->createStub(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn(null);

    $handler = new EditInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $this->createStub(EquipmentValidationPort::class),
      facilityValidation: $this->createStub(FacilityValidationPort::class),
      checklistValidation: $this->createStub(ChecklistValidationPort::class),
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new EditInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationMismatch(): void
  {
    $inspection = $this->makeDraftInspection();

    $repository = $this->createStub(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);

    $handler = new EditInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $this->createStub(EquipmentValidationPort::class),
      facilityValidation: $this->createStub(FacilityValidationPort::class),
      checklistValidation: $this->createStub(ChecklistValidationPort::class),
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new EditInspectionCommand(
      organizationId: '550e8400-e29b-41d4-a716-999999999999',
      inspectionId: self::INSP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentIdNullWhenRequired(): void
  {
    $inspection = $this->makeDraftInspection();

    $repository = $this->createStub(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);

    $handler = new EditInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $this->createStub(EquipmentValidationPort::class),
      facilityValidation: $this->createStub(FacilityValidationPort::class),
      checklistValidation: $this->createStub(ChecklistValidationPort::class),
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Field "equipmentId" cannot be null when provided.');

    $handler->__invoke(new EditInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      equipmentId: null,
      hasEquipmentId: true,
    ));
  }

  #[Test]
  public function testInvokeValidatesEquipmentWhenEquipmentIdProvided(): void
  {
    $inspection = $this->makeDraftInspection();

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);
    $repository->expects(self::once())->method('save');

    /** @var EquipmentValidationPort&MockObject $equipmentValidation */
    $equipmentValidation = $this->createMock(EquipmentValidationPort::class);
    $equipmentValidation->expects(self::once())
      ->method('assertEquipmentExists')
      ->with(self::EQUIP_ID, self::ORG_ID);

    $handler = new EditInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $equipmentValidation,
      facilityValidation: $this->createStub(FacilityValidationPort::class),
      checklistValidation: $this->createStub(ChecklistValidationPort::class),
    );

    $handler->__invoke(new EditInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      equipmentId: self::EQUIP_ID,
      hasEquipmentId: true,
    ));
  }

  #[Test]
  public function testInvokeValidatesFacilityWhenFacilityIdProvided(): void
  {
    $inspection = $this->makeDraftInspection();

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);
    $repository->expects(self::once())->method('save');

    /** @var FacilityValidationPort&MockObject $facilityValidation */
    $facilityValidation = $this->createMock(FacilityValidationPort::class);
    $facilityValidation->expects(self::once())
      ->method('assertFacilityIsUsable')
      ->with('550e8400-e29b-41d4-a716-446655440004', self::ORG_ID);

    $handler = new EditInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $this->createStub(EquipmentValidationPort::class),
      facilityValidation: $facilityValidation,
      checklistValidation: $this->createStub(ChecklistValidationPort::class),
    );

    $handler->__invoke(new EditInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      facilityId: '550e8400-e29b-41d4-a716-446655440004',
      hasFacilityId: true,
    ));
  }

  #[Test]
  public function testInvokeValidatesChecklistWhenChecklistIdProvided(): void
  {
    $inspection = $this->makeDraftInspection();

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);
    $repository->expects(self::once())->method('save');

    /** @var ChecklistValidationPort&MockObject $checklistValidation */
    $checklistValidation = $this->createMock(ChecklistValidationPort::class);
    $checklistValidation->expects(self::once())
      ->method('assertChecklistIsUsable')
      ->with('550e8400-e29b-41d4-a716-446655440005', self::ORG_ID);

    $handler = new EditInspectionHandler(
      inspectionRepository: $repository,
      equipmentValidation: $this->createStub(EquipmentValidationPort::class),
      facilityValidation: $this->createStub(FacilityValidationPort::class),
      checklistValidation: $checklistValidation,
    );

    $handler->__invoke(new EditInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      checklistId: '550e8400-e29b-41d4-a716-446655440005',
      hasChecklistId: true,
    ));
  }

  // #region Helpers
  private function makeDraftInspection(): Inspection
  {
    return Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
    );
  }
  // #endregion
}
