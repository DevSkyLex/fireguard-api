<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\AssignToFacility;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilityNamingPort, FacilityValidationPort, TagRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\AssignToFacility\{AssignToFacilityCommand, AssignToFacilityHandler, AssignToFacilityResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(AssignToFacilityHandler::class)]
final class AssignToFacilityHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440003';

  // #region Methods
  #[Test]
  public function testInvokeAssignsEquipmentToFacility(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findById')
      ->willReturn($equipment);
    $equipmentRepository->expects(self::once())
      ->method('save');

    /** @var FacilityValidationPort&MockObject $facilityValidation */
    $facilityValidation = $this->createMock(FacilityValidationPort::class);
    $facilityValidation->expects(self::once())
      ->method('assertFacilityIsAssignable')
      ->with(self::FACILITY_ID, self::ORG_ID);

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    $handler = new AssignToFacilityHandler(
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentRepository: $equipmentRepository,
      facilityValidation: $facilityValidation,
      tagRepository: $tagRepository,
    );

    $result = $handler->__invoke(new AssignToFacilityCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      facilityId: self::FACILITY_ID,
    ));

    self::assertInstanceOf(AssignToFacilityResult::class, $result);
    self::assertSame(self::FACILITY_ID, $result->facilityId);
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentNotFound(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);
    $equipmentRepository->expects(self::never())->method('save');

    /** @var FacilityValidationPort&MockObject $facilityValidation */
    $facilityValidation = $this->createMock(FacilityValidationPort::class);
    $facilityValidation->expects(self::never())->method('assertFacilityIsAssignable');

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    $handler = new AssignToFacilityHandler(
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentRepository: $equipmentRepository,
      facilityValidation: $facilityValidation,
      tagRepository: $tagRepository,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new AssignToFacilityCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      facilityId: self::FACILITY_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentBelongsToAnotherOrganization(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440099'),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findById')
      ->willReturn($equipment);
    $equipmentRepository->expects(self::never())->method('save');

    /** @var FacilityValidationPort&MockObject $facilityValidation */
    $facilityValidation = $this->createMock(FacilityValidationPort::class);
    $facilityValidation->expects(self::never())->method('assertFacilityIsAssignable');

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    $handler = new AssignToFacilityHandler(
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentRepository: $equipmentRepository,
      facilityValidation: $facilityValidation,
      tagRepository: $tagRepository,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new AssignToFacilityCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      facilityId: self::FACILITY_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityNotFound(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findById')
      ->willReturn($equipment);
    $equipmentRepository->expects(self::never())->method('save');

    /** @var FacilityValidationPort&MockObject $facilityValidation */
    $facilityValidation = $this->createMock(FacilityValidationPort::class);
    $facilityValidation->expects(self::once())
      ->method('assertFacilityIsAssignable')
      ->willThrowException(new InvalidArgumentException('Facility with ID "' . self::FACILITY_ID . '" not found.'));

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    $handler = new AssignToFacilityHandler(
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentRepository: $equipmentRepository,
      facilityValidation: $facilityValidation,
      tagRepository: $tagRepository,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new AssignToFacilityCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      facilityId: self::FACILITY_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityIsArchived(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findById')
      ->willReturn($equipment);
    $equipmentRepository->expects(self::never())->method('save');

    /** @var FacilityValidationPort&MockObject $facilityValidation */
    $facilityValidation = $this->createMock(FacilityValidationPort::class);
    $facilityValidation->expects(self::once())
      ->method('assertFacilityIsAssignable')
      ->willThrowException(new InvalidArgumentException('Facility with ID "' . self::FACILITY_ID . '" is archived and cannot be used.'));

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    $handler = new AssignToFacilityHandler(
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentRepository: $equipmentRepository,
      facilityValidation: $facilityValidation,
      tagRepository: $tagRepository,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new AssignToFacilityCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      facilityId: self::FACILITY_ID,
    ));
  }

  #[Test]
  public function testInvokeRejectsAMalformedEquipmentId(): void
  {
    $handler = new AssignToFacilityHandler(
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentRepository: $this->createStub(EquipmentRepositoryPort::class),
      facilityValidation: $this->createStub(FacilityValidationPort::class),
      tagRepository: $this->createStub(TagRepositoryPort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new AssignToFacilityCommand(
      organizationId: self::ORG_ID,
      equipmentId: 'not-a-uuid',
      facilityId: self::FACILITY_ID,
    ));
  }

  #[Test]
  public function testInvokeUsesTheProvidedInstalledAtDate(): void
  {
    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($this->equipment());

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    $handler = new AssignToFacilityHandler(
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentRepository: $equipmentRepository,
      facilityValidation: $this->createStub(FacilityValidationPort::class),
      tagRepository: $tagRepository,
    );

    $result = $handler->__invoke(new AssignToFacilityCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      facilityId: self::FACILITY_ID,
      installedAt: '2026-03-04T10:00:00+00:00',
    ));

    self::assertInstanceOf(AssignToFacilityResult::class, $result);
    self::assertSame('2026-03-04T10:00:00+00:00', $result->installedAt);
  }

  #[Test]
  public function testInvokeRejectsAMalformedInstalledAtDate(): void
  {
    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($this->equipment());

    $handler = new AssignToFacilityHandler(
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentRepository: $equipmentRepository,
      facilityValidation: $this->createStub(FacilityValidationPort::class),
      tagRepository: $this->createStub(TagRepositoryPort::class),
    );

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Invalid date format for installedAt: "yesterday-ish".');

    $handler->__invoke(new AssignToFacilityCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      facilityId: self::FACILITY_ID,
      installedAt: 'yesterday-ish',
    ));
  }

  #[Test]
  public function testInvokeResolvesTheAssignedFacilityName(): void
  {
    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($this->equipment());

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    /** @var FacilityNamingPort&MockObject $facilityNaming */
    $facilityNaming = $this->createMock(FacilityNamingPort::class);
    $facilityNaming->expects(self::once())
      ->method('findNamesByIds')
      ->with([self::FACILITY_ID])
      ->willReturn([self::FACILITY_ID => 'Main Building']);

    $handler = new AssignToFacilityHandler(
      equipmentRepository: $equipmentRepository,
      facilityValidation: $this->createStub(FacilityValidationPort::class),
      tagRepository: $tagRepository,
      facilityNaming: $facilityNaming,
    );

    $result = $handler->__invoke(new AssignToFacilityCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      facilityId: self::FACILITY_ID,
    ));

    self::assertSame('Main Building', $result->facilityName);
  }

  private function equipment(): Equipment
  {
    return Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
  }

  // #endregion
}
