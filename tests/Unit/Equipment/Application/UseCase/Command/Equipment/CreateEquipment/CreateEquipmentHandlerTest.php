<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\CreateEquipment;

use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Equipment\Application\Port\Outbound\EquipmentRepositoryPort;
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentHandler, CreateEquipmentResult};
use Equipment\Domain\Exception\EquipmentSerialNumberAlreadyExistsException;
use Equipment\Domain\ValueObject\EquipmentId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;

#[CoversClass(CreateEquipmentHandler::class)]
final class CreateEquipmentHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeThrowsInvalidArgumentOnInvalidType(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(EquipmentId::class)
      ->willReturn(new EquipmentId('550e8400-e29b-41d4-a716-446655440900'));

    $handler = new CreateEquipmentHandler(
      equipmentRepository: $repository,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new CreateEquipmentCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440981',
      type: 'invalid_equipment_type',
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

    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save')
      ->willThrowException(new UniqueConstraintViolationException($driverException, null));

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(EquipmentId::class)
      ->willReturn(new EquipmentId('550e8400-e29b-41d4-a716-446655440900'));

    $handler = new CreateEquipmentHandler(
      equipmentRepository: $repository,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(EquipmentSerialNumberAlreadyExistsException::class);
    $this->expectExceptionMessage('Serial number "EXT-2026-001" already exists in this organization.');

    $handler->__invoke(new CreateEquipmentCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440982',
      type: 'fire_extinguisher',
      serialNumber: 'EXT-2026-001',
    ));
  }

  #[Test]
  public function testInvokeReturnsResultOnSuccess(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(EquipmentId::class)
      ->willReturn(new EquipmentId('550e8400-e29b-41d4-a716-446655440903'));

    $handler = new CreateEquipmentHandler(
      equipmentRepository: $repository,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new CreateEquipmentCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440983',
      type: 'fire_extinguisher',
      brand: 'Sicli',
      model: 'Pro 6',
    ));

    self::assertInstanceOf(CreateEquipmentResult::class, $result);
    self::assertSame('550e8400-e29b-41d4-a716-446655440903', $result->equipmentId);
    self::assertSame('fire_extinguisher', $result->type);
    self::assertSame('Sicli', $result->brand);
    self::assertSame('in_stock', $result->status);
  }
}
