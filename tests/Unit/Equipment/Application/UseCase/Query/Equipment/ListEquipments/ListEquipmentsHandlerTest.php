<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\Equipment\ListEquipments;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Equipment\Application\UseCase\Query\Equipment\ListEquipments\{ListEquipmentsHandler, ListEquipmentsQuery};
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};

#[CoversClass(ListEquipmentsHandler::class)]
final class ListEquipmentsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655448001';

  private const string EQUIP_ID_1 = '550e8400-e29b-41d4-a716-446655448002';

  private const string EQUIP_ID_2 = '550e8400-e29b-41d4-a716-446655448003';

  #[Test]
  public function testInvokeThrowsInvalidArgumentOnInvalidOrganizationId(): void
  {
    $handler = new ListEquipmentsHandler(
      equipmentRepository: $this->createMock(EquipmentRepositoryPort::class),
      tagRepository: $this->createMock(TagRepositoryPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ListEquipmentsQuery(
      organizationId: 'invalid-uuid',
    ));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentOnInvalidType(): void
  {
    $handler = new ListEquipmentsHandler(
      equipmentRepository: $this->createMock(EquipmentRepositoryPort::class),
      tagRepository: $this->createMock(TagRepositoryPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      type: 'not_a_valid_type',
    ));
  }

  #[Test]
  public function testInvokeReturnsEmptyPaginatedResultWhenNoEquipments(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([]);
    $equipmentRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(0);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())
      ->method('findTagsByEquipmentIds')
      ->willReturn([]);

    $handler = new ListEquipmentsHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
    );

    $result = $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      pagination: new Pagination(limit: 10, offset: 0),
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertSame(0, $result->total);
    self::assertCount(0, $result->items);
  }

  #[Test]
  public function testInvokeReturnsPaginatedResultWithItems(): void
  {
    $equipment1 = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID_1),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipment2 = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID_2),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::SMOKE_DETECTOR,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([$equipment1, $equipment2]);
    $equipmentRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(2);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())
      ->method('findTagsByEquipmentIds')
      ->willReturn([]);

    $handler = new ListEquipmentsHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
    );

    $result = $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      pagination: new Pagination(limit: 10, offset: 0),
    ));

    self::assertSame(2, $result->total);
    self::assertCount(2, $result->items);
    self::assertInstanceOf(GetEquipmentResult::class, $result->items[0]);
    self::assertSame(self::EQUIP_ID_1, $result->items[0]->equipmentId);
    self::assertSame('fire_extinguisher', $result->items[0]->type);
    self::assertSame(self::EQUIP_ID_2, $result->items[1]->equipmentId);
    self::assertSame('smoke_detector', $result->items[1]->type);
  }

  #[Test]
  public function testInvokePassesPaginationToRepository(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(
        self::anything(),
        null,
        null,
        null,
        5,
        10,
      )
      ->willReturn([]);
    $equipmentRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(0);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())
      ->method('findTagsByEquipmentIds')
      ->willReturn([]);

    $handler = new ListEquipmentsHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
    );

    $result = $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      pagination: new Pagination(limit: 5, offset: 10),
    ));

    self::assertSame(5, $result->limit);
    self::assertSame(10, $result->offset);
  }
}
