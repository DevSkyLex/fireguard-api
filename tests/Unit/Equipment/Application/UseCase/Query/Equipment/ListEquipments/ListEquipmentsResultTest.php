<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\Equipment\ListEquipments;

use DateTimeImmutable;
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Equipment\Application\UseCase\Query\Equipment\ListEquipments\ListEquipmentsResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test ListEquipmentsResultTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListEquipmentsResult::class)]
final class ListEquipmentsResultTest extends TestCase
{
  #[Test]
  public function testItExposesAnEmptyList(): void
  {
    $result = new ListEquipmentsResult([]);

    self::assertSame([], $result->equipments);
    self::assertInstanceOf(ResultMessage::class, $result);
  }

  #[Test]
  public function testItPreservesTheEquipmentOrder(): void
  {
    $first = $this->equipment('550e8400-e29b-41d4-a716-446655490001');
    $second = $this->equipment('550e8400-e29b-41d4-a716-446655490002');

    $result = new ListEquipmentsResult([$first, $second]);

    self::assertCount(2, $result->equipments);
    self::assertSame($first, $result->equipments[0]);
    self::assertSame($second, $result->equipments[1]);
  }

  private function equipment(string $id): GetEquipmentResult
  {
    $now = new DateTimeImmutable('2026-03-02T10:00:00+00:00');

    return new GetEquipmentResult(
      equipmentId: $id,
      organizationId: '550e8400-e29b-41d4-a716-446655490000',
      facilityId: null,
      type: 'fire_extinguisher',
      subType: null,
      brand: null,
      model: null,
      serialNumber: null,
      locationLabel: null,
      status: 'in_stock',
      installedAt: null,
      commissionedAt: null,
      tags: [],
      createdAt: $now,
      updatedAt: $now,
    );
  }
}
