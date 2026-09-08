<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\UpdateEquipment;

use DateTimeImmutable;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilityNamingPort, TagRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\UpdateEquipment\{UpdateEquipmentCommand, UpdateEquipmentHandler, UpdateEquipmentResult};
use Equipment\Domain\Exception\{EquipmentNotFoundException, EquipmentSerialNumberAlreadyExistsException};
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentFacilityId, EquipmentId, EquipmentOrganizationId, EquipmentType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(UpdateEquipmentHandler::class)]
final class UpdateEquipmentHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeThrowsNotFoundWhenEquipmentMissing(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);
    $repository->expects(self::never())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    $handler = new UpdateEquipmentHandler(equipmentRepository: $repository, tagRepository: $tagRepository, facilityNaming: $this->createStub(FacilityNamingPort::class));

    $this->expectException(EquipmentNotFoundException::class);
    $this->expectExceptionMessage('Equipment with ID "550e8400-e29b-41d4-a716-446655440999" not found.');

    $handler->__invoke(new UpdateEquipmentCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440980',
      equipmentId: '550e8400-e29b-41d4-a716-446655440999',
      type: 'fire_extinguisher',
    ));
  }

  #[Test]
  public function testInvokeThrowsSerialNumberAlreadyExistsOnUniqueConstraintViolation(): void
  {
    $existingEquipmentId = '550e8400-e29b-41d4-a716-446655440901';
    $organizationId = '550e8400-e29b-41d4-a716-446655440902';

    $equipment = Equipment::create(
      id: new EquipmentId($existingEquipmentId),
      organizationId: new EquipmentOrganizationId($organizationId),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($equipment);
    $repository->expects(self::once())
      ->method('save')
      ->willThrowException(EquipmentSerialNumberAlreadyExistsException::withSerialNumber('EXT-DUPLICATE'));

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    $handler = new UpdateEquipmentHandler(equipmentRepository: $repository, tagRepository: $tagRepository, facilityNaming: $this->createStub(FacilityNamingPort::class));

    $this->expectException(EquipmentSerialNumberAlreadyExistsException::class);
    $this->expectExceptionMessage('Serial number "EXT-DUPLICATE" already exists in this organization.');

    $handler->__invoke(new UpdateEquipmentCommand(
      organizationId: $organizationId,
      equipmentId: $existingEquipmentId,
      type: 'fire_extinguisher',
      serialNumber: 'EXT-DUPLICATE',
    ));
  }

  #[Test]
  public function testInvokeReturnsResultOnSuccess(): void
  {
    $equipmentId = '550e8400-e29b-41d4-a716-446655440903';
    $organizationId = '550e8400-e29b-41d4-a716-446655440904';

    $equipment = Equipment::create(
      id: new EquipmentId($equipmentId),
      organizationId: new EquipmentOrganizationId($organizationId),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($equipment);
    $repository->expects(self::once())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    $handler = new UpdateEquipmentHandler(equipmentRepository: $repository, tagRepository: $tagRepository, facilityNaming: $this->createStub(FacilityNamingPort::class));

    $result = $handler->__invoke(new UpdateEquipmentCommand(
      organizationId: $organizationId,
      equipmentId: $equipmentId,
      type: 'smoke_detector',
      brand: 'Fireman',
      model: 'Pro-2000',
      serialNumber: 'SN-UPDATED',
      locationLabel: '3rd floor',
    ));

    self::assertInstanceOf(UpdateEquipmentResult::class, $result);
    self::assertSame($equipmentId, $result->equipmentId);
    self::assertSame($organizationId, $result->organizationId);
    self::assertSame('smoke_detector', $result->type);
    self::assertSame('Fireman', $result->brand);
    self::assertSame('Pro-2000', $result->model);
    self::assertSame('SN-UPDATED', $result->serialNumber);
    self::assertSame('3rd floor', $result->locationLabel);
    self::assertSame('in_stock', $result->status);
    self::assertSame([], $result->tags);
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentExceptionOnInvalidType(): void
  {
    $equipmentId = '550e8400-e29b-41d4-a716-446655440905';
    $organizationId = '550e8400-e29b-41d4-a716-446655440906';

    $equipment = Equipment::create(
      id: new EquipmentId($equipmentId),
      organizationId: new EquipmentOrganizationId($organizationId),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($equipment);
    $repository->expects(self::never())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    $handler = new UpdateEquipmentHandler(equipmentRepository: $repository, tagRepository: $tagRepository, facilityNaming: $this->createStub(FacilityNamingPort::class));

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new UpdateEquipmentCommand(
      organizationId: $organizationId,
      equipmentId: $equipmentId,
      type: 'invalid_type_xyz',
    ));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentForMalformedIdentifiers(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('findById');

    $handler = new UpdateEquipmentHandler(
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentRepository: $repository,
      tagRepository: $this->createStub(TagRepositoryPort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new UpdateEquipmentCommand(
      organizationId: 'not-a-uuid',
      equipmentId: 'also-not-a-uuid',
      type: 'fire_extinguisher',
    ));
  }

  #[Test]
  public function testInvokeResolvesTheAssignedFacilityName(): void
  {
    $equipmentId = '550e8400-e29b-41d4-a716-446655440910';
    $organizationId = '550e8400-e29b-41d4-a716-446655440911';
    $facilityId = '550e8400-e29b-41d4-a716-446655440912';

    $equipment = Equipment::create(
      id: new EquipmentId($equipmentId),
      organizationId: new EquipmentOrganizationId($organizationId),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $equipment->assignToFacility(EquipmentFacilityId::fromString($facilityId), new DateTimeImmutable());

    $repository = $this->createStub(EquipmentRepositoryPort::class);
    $repository->method('findById')->willReturn($equipment);

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    /** @var FacilityNamingPort&MockObject $facilityNaming */
    $facilityNaming = $this->createMock(FacilityNamingPort::class);
    $facilityNaming->expects(self::once())
      ->method('findNamesByIds')
      ->with([$facilityId])
      ->willReturn([$facilityId => 'Main Building']);

    $handler = new UpdateEquipmentHandler(equipmentRepository: $repository, tagRepository: $tagRepository, facilityNaming: $facilityNaming);

    $result = $handler->__invoke(new UpdateEquipmentCommand(
      organizationId: $organizationId,
      equipmentId: $equipmentId,
      type: 'fire_extinguisher',
    ));

    self::assertSame('Main Building', $result->facilityName);
  }
}
