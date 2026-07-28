<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Facility\ListFacilities;

use DateTimeImmutable;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Application\UseCase\Query\Facility\ListFacilities\ListFacilitiesResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test ListFacilitiesResultTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListFacilitiesResult::class)]
final class ListFacilitiesResultTest extends TestCase
{
  #[Test]
  public function testItExposesAnEmptyList(): void
  {
    $result = new ListFacilitiesResult([]);

    self::assertSame([], $result->facilities);
    self::assertInstanceOf(ResultMessage::class, $result);
  }

  #[Test]
  public function testItPreservesTheFacilityOrder(): void
  {
    $now = new DateTimeImmutable('2026-03-02T10:00:00+00:00');

    $first = new GetFacilityResult(
      facilityId: '550e8400-e29b-41d4-a716-446655492001',
      organizationId: '550e8400-e29b-41d4-a716-446655492000',
      parentFacilityId: null,
      type: 'site',
      name: 'Site',
      code: 'S-1',
      status: 'active',
      address: null,
      metadata: [],
      createdAt: $now,
      updatedAt: $now,
      hasChildren: true,
      equipmentCount: 4,
    );

    $second = new GetFacilityResult(
      facilityId: '550e8400-e29b-41d4-a716-446655492002',
      organizationId: '550e8400-e29b-41d4-a716-446655492000',
      parentFacilityId: '550e8400-e29b-41d4-a716-446655492001',
      type: 'building',
      name: 'Building',
      code: null,
      status: 'active',
      address: null,
      metadata: [],
      createdAt: $now,
      updatedAt: $now,
    );

    $result = new ListFacilitiesResult([$first, $second]);

    self::assertCount(2, $result->facilities);
    self::assertTrue($result->facilities[0]->hasChildren);
    self::assertSame(4, $result->facilities[0]->equipmentCount);
    self::assertSame('550e8400-e29b-41d4-a716-446655492001', $result->facilities[1]->parentFacilityId);
  }
}
