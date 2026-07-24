<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\Event\Inspection;

use DateTimeImmutable;
use Inspection\Domain\Event\Inspection\InspectionClosedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InspectionClosedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionClosedEvent::class)]
final class InspectionClosedEventTest extends TestCase
{
  #[Test]
  public function itExposesItsPayloadAndOccurredAt(): void
  {
    $event = new InspectionClosedEvent(
      organizationId: 'org-1',
      inspectionId: 'insp-1',
      equipmentId: 'eq-1',
      result: 'partial',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('insp-1', $event->inspectionId);
    self::assertSame('eq-1', $event->equipmentId);
    self::assertSame('partial', $event->result);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
}
