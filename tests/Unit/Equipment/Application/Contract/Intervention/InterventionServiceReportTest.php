<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\Contract\Intervention;

use Equipment\Application\Contract\Intervention\{InterventionServiceReport, ServicedEquipmentEntry};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionServiceReportTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionServiceReport::class)]
final class InterventionServiceReportTest extends TestCase
{
  #[Test]
  public function itExposesReadonlyProperties(): void
  {
    $entry = new ServicedEquipmentEntry('equip-1', 'replaced', 'token-1', 'wi-1');
    $report = new InterventionServiceReport(42, 'actor-1', [$entry]);

    self::assertSame(42, $report->number);
    self::assertSame('actor-1', $report->actorId);
    self::assertCount(1, $report->equipment);
    self::assertSame($entry, $report->equipment[0]);
  }

  #[Test]
  public function itAllowsNullActorAndEmptyEquipment(): void
  {
    $report = new InterventionServiceReport(1, null, []);

    self::assertNull($report->actorId);
    self::assertSame([], $report->equipment);
  }
}
