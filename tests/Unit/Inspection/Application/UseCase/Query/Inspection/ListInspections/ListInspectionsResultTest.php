<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Inspection\ListInspections;

use DateTimeImmutable;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Application\UseCase\Query\Inspection\ListInspections\ListInspectionsResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListInspectionsResultTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListInspectionsResult::class)]
final class ListInspectionsResultTest extends TestCase
{
  #[Test]
  public function testItExposesTheInspectionList(): void
  {
    $now = new DateTimeImmutable('2026-02-01T08:00:00+00:00');
    $inspection = new GetInspectionResult(
      inspectionId: 'inspection-1',
      organizationId: 'org-1',
      equipmentId: 'equipment-1',
      facilityId: null,
      result: 'pass',
      status: 'draft',
      performedAt: '2026-02-01T08:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'Inspector',
      inspectorUserId: 'user-1',
      inspectorOrganizationName: null,
      checklistId: null,
      notes: null,
      signature: null,
      nonConformitiesCount: 0,
      createdAt: $now,
      updatedAt: $now,
    );

    $result = new ListInspectionsResult([$inspection]);

    self::assertSame([$inspection], $result->inspections);
  }

  #[Test]
  public function testItAcceptsAnEmptyList(): void
  {
    self::assertSame([], new ListInspectionsResult([])->inspections);
  }
}
