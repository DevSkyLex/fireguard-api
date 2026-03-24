<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\Equipment\GetEquipment;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\{GetEquipmentHandler, GetEquipmentQuery, GetEquipmentResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetEquipmentHandler::class)]
final class GetEquipmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655447001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655447002';

  #[Test]
  public function testInvokeThrowsInvalidArgumentOnInvalidEquipmentId(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::never())->method('findById');

    $handler = new GetEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $this->createMock(TagRepositoryPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new GetEquipmentQuery(
      organizationId: self::ORG_ID,
      equipmentId: 'not-a-uuid',
    ));
  }

  #[Test]
  public function testInvokeThrowsEquipmentNotFoundWhenRepositoryReturnsNull(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new GetEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $this->createMock(TagRepositoryPort::class),
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new GetEquipmentQuery(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsEquipmentNotFoundWhenOrganizationMismatch(): void
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

    $handler = new GetEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $this->createMock(TagRepositoryPort::class),
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new GetEquipmentQuery(
      organizationId: '550e8400-e29b-41d4-a716-446655447999',
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeReturnsResultOnSuccess(): void
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

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())
      ->method('findByEquipmentId')
      ->willReturn([]);

    $handler = new GetEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
    );

    $result = $handler->__invoke(new GetEquipmentQuery(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertInstanceOf(GetEquipmentResult::class, $result);
    self::assertSame(self::EQUIP_ID, $result->equipmentId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertSame('fire_extinguisher', $result->type);
    self::assertSame('in_stock', $result->status);
    self::assertSame([], $result->tags);
  }
}
