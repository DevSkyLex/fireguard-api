<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\NonConformity\ListNonConformities;

use DateTimeImmutable;
use Inspection\Application\UseCase\Query\NonConformity\ListNonConformities\{ListNonConformitiesResult, NonConformityResult};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListNonConformitiesResultTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListNonConformitiesResult::class)]
final class ListNonConformitiesResultTest extends TestCase
{
  #[Test]
  public function testItExposesTheNonConformityList(): void
  {
    $now = new DateTimeImmutable('2026-02-01T08:00:00+00:00');
    $nonConformity = new NonConformityResult(
      nonConformityId: 'nc-1',
      inspectionId: 'inspection-1',
      description: 'Extinguisher pressure too low',
      severity: 'high',
      status: 'open',
      dueAt: null,
      resolvedAt: null,
      notes: null,
      createdAt: $now,
      updatedAt: $now,
    );

    $result = new ListNonConformitiesResult([$nonConformity]);

    self::assertSame([$nonConformity], $result->nonConformities);
  }

  #[Test]
  public function testItAcceptsAnEmptyList(): void
  {
    self::assertSame([], new ListNonConformitiesResult([])->nonConformities);
  }
}
