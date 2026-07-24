<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Contract\Intervention;

use DateTimeImmutable;
use Organization\Application\Contract\Intervention\RecentInterventionSummary;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RecentInterventionSummary.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RecentInterventionSummary::class)]
final class RecentInterventionSummaryTest extends TestCase
{
  #[Test]
  public function testExposesReadonlyProperties(): void
  {
    $dueAt = new DateTimeImmutable('2026-08-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-07-24T00:00:00+00:00');

    $summary = new RecentInterventionSummary(
      'intervention-1',
      42,
      'Extinguisher check',
      'draft',
      'high',
      'site-1',
      'member-1',
      $dueAt,
      $updatedAt,
    );

    self::assertSame('intervention-1', $summary->id);
    self::assertSame(42, $summary->number);
    self::assertSame('Extinguisher check', $summary->name);
    self::assertSame('draft', $summary->status);
    self::assertSame('high', $summary->priority);
    self::assertSame('site-1', $summary->siteId);
    self::assertSame('member-1', $summary->responsibleMemberId);
    self::assertSame($dueAt, $summary->dueAt);
    self::assertSame($updatedAt, $summary->updatedAt);
  }

  #[Test]
  public function testSupportsNullableFields(): void
  {
    $summary = new RecentInterventionSummary(
      'intervention-2',
      1,
      'Unassigned task',
      'open',
      'low',
      null,
      null,
      null,
      new DateTimeImmutable('2026-07-24T00:00:00+00:00'),
    );

    self::assertNull($summary->siteId);
    self::assertNull($summary->responsibleMemberId);
    self::assertNull($summary->dueAt);
  }
}
