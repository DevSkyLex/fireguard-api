<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Facility\GetFacilityChildren;

use DateTimeImmutable;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Application\UseCase\Query\Facility\GetFacilityChildren\GetFacilityChildrenResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test GetFacilityChildrenResultTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityChildrenResult::class)]
final class GetFacilityChildrenResultTest extends TestCase
{
  #[Test]
  public function testALeafFacilityHasNoChildren(): void
  {
    $result = new GetFacilityChildrenResult([]);

    self::assertSame([], $result->items);
    self::assertInstanceOf(ResultMessage::class, $result);
  }

  #[Test]
  public function testItPreservesTheChildOrder(): void
  {
    $first = $this->facility('550e8400-e29b-41d4-a716-446655491001', 'Building A');
    $second = $this->facility('550e8400-e29b-41d4-a716-446655491002', 'Building B');

    $result = new GetFacilityChildrenResult([$first, $second]);

    self::assertCount(2, $result->items);
    self::assertSame('Building A', $result->items[0]->name);
    self::assertSame('Building B', $result->items[1]->name);
  }

  private function facility(string $id, string $name): GetFacilityResult
  {
    $now = new DateTimeImmutable('2026-03-02T10:00:00+00:00');

    return new GetFacilityResult(
      facilityId: $id,
      organizationId: '550e8400-e29b-41d4-a716-446655491000',
      parentFacilityId: null,
      type: 'building',
      name: $name,
      code: null,
      status: 'active',
      address: null,
      metadata: [],
      createdAt: $now,
      updatedAt: $now,
    );
  }
}
