<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\UpdateEquipment;

use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\UpdateEquipment\{UpdateEquipmentCommand, UpdateEquipmentHandler};
use Equipment\Domain\Exception\{EquipmentNotFoundException, EquipmentSerialNumberAlreadyExistsException};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);

    $handler = new UpdateEquipmentHandler(equipmentRepository: $repository, tagRepository: $tagRepository);

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
    $driverException = new class ('SQLSTATE[23505]: duplicate key value violates unique constraint "uniq_equipment_organization_serial"') extends RuntimeException implements DoctrineDriverException {
      public function getSQLState(): string
      {
        return '23505';
      }
    };

    // Build a real equipment instance to return from findById
    $existingEquipmentId = '550e8400-e29b-41d4-a716-446655440901';
    $organizationId = '550e8400-e29b-41d4-a716-446655440902';

    // We need a real Equipment model; use the factory helpers
    $equipmentId = new \Equipment\Domain\ValueObject\EquipmentId($existingEquipmentId);
    $orgId = new \Equipment\Domain\ValueObject\EquipmentOrganizationId($organizationId);
    $equipment = \Equipment\Domain\Model\Equipment\Equipment::create(
      id: $equipmentId,
      organizationId: $orgId,
      type: \Equipment\Domain\ValueObject\EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($equipment);
    $repository->expects(self::once())
      ->method('save')
      ->willThrowException(new UniqueConstraintViolationException($driverException, null));

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);

    $handler = new UpdateEquipmentHandler(equipmentRepository: $repository, tagRepository: $tagRepository);

    $this->expectException(EquipmentSerialNumberAlreadyExistsException::class);
    $this->expectExceptionMessage('Serial number "EXT-DUPLICATE" already exists in this organization.');

    $handler->__invoke(new UpdateEquipmentCommand(
      organizationId: $organizationId,
      equipmentId: $existingEquipmentId,
      type: 'fire_extinguisher',
      serialNumber: 'EXT-DUPLICATE',
    ));
  }
}
