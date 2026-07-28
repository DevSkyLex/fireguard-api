<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\NonConformity\ListOrganizationNonConformities;

use DateTimeImmutable;
use Inspection\Application\UseCase\Query\NonConformity\ListOrganizationNonConformities\{ListOrganizationNonConformitiesResult, OrganizationNonConformityResult};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListOrganizationNonConformitiesResultTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListOrganizationNonConformitiesResult::class)]
final class ListOrganizationNonConformitiesResultTest extends TestCase
{
  #[Test]
  public function testItExposesTheNonConformityList(): void
  {
    $now = new DateTimeImmutable('2026-02-01T08:00:00+00:00');
    $nonConformity = new OrganizationNonConformityResult(
      nonConformityId: 'nc-1',
      inspectionId: 'inspection-1',
      description: 'Blocked emergency exit',
      severity: 'critical',
      status: 'open',
      dueAt: '2026-03-01T08:00:00+00:00',
      resolvedAt: null,
      notes: null,
      createdAt: $now,
      updatedAt: $now,
      equipmentId: 'equipment-1',
      equipmentSerialNumber: 'SN-1',
    );

    $result = new ListOrganizationNonConformitiesResult([$nonConformity]);

    self::assertSame([$nonConformity], $result->nonConformities);
  }

  #[Test]
  public function testItAcceptsAnEmptyList(): void
  {
    self::assertSame([], new ListOrganizationNonConformitiesResult([])->nonConformities);
  }
}
