<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Facility\GetFacilityDescendants;

use DateTimeImmutable;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Application\UseCase\Query\Facility\GetFacilityDescendants\GetFacilityDescendantsResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetFacilityDescendantsResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityDescendantsResult::class)]
final class GetFacilityDescendantsResultTest extends TestCase
{
  #[Test]
  public function testConstructorExposesItems(): void
  {
    $item = new GetFacilityResult(
      facilityId: 'fac-1',
      organizationId: 'org-1',
      parentFacilityId: null,
      type: 'site',
      name: 'Root',
      code: null,
      status: 'active',
      address: null,
      metadata: [],
      createdAt: new DateTimeImmutable(),
      updatedAt: new DateTimeImmutable(),
      hasChildren: false,
      equipmentCount: 0,
    );

    $result = new GetFacilityDescendantsResult([$item]);

    self::assertCount(1, $result->items);
    self::assertSame($item, $result->items[0]);
  }

  #[Test]
  public function testConstructorAcceptsEmptyList(): void
  {
    $result = new GetFacilityDescendantsResult([]);

    self::assertSame([], $result->items);
  }
}
