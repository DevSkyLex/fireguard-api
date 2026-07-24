<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Facility\GetFacilityDescendants;

use Facility\Application\UseCase\Query\Facility\GetFacilityDescendants\GetFacilityDescendantsQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

/**
 * Test GetFacilityDescendantsQuery.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityDescendantsQuery::class)]
final class GetFacilityDescendantsQueryTest extends TestCase
{
  #[Test]
  public function testConstructorAppliesDefaults(): void
  {
    $query = new GetFacilityDescendantsQuery(
      organizationId: 'org-1',
      facilityId: 'fac-1',
    );

    self::assertSame('org-1', $query->organizationId);
    self::assertSame('fac-1', $query->facilityId);
    self::assertFalse($query->includeArchived);
    self::assertNull($query->search);
    self::assertSame('name', $query->sorting->field);
    self::assertSame(SortDirection::ASC, $query->sorting->direction);
  }

  #[Test]
  public function testConstructorRoundTripsExplicitValues(): void
  {
    $sorting = new Sorting('createdAt', SortDirection::DESC);

    $query = new GetFacilityDescendantsQuery(
      organizationId: 'org-2',
      facilityId: 'fac-2',
      includeArchived: true,
      search: 'warehouse',
      sorting: $sorting,
    );

    self::assertTrue($query->includeArchived);
    self::assertSame('warehouse', $query->search);
    self::assertSame($sorting, $query->sorting);
  }
}
