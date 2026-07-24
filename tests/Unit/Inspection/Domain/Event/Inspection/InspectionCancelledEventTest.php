<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\Event\Inspection;

use DateTimeImmutable;
use Inspection\Domain\Event\Inspection\InspectionCancelledEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InspectionCancelledEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionCancelledEvent::class)]
final class InspectionCancelledEventTest extends TestCase
{
  #[Test]
  public function itExposesItsPayloadAndOccurredAt(): void
  {
    $event = new InspectionCancelledEvent(
      organizationId: 'org-1',
      inspectionId: 'insp-1',
      equipmentId: 'eq-1',
      previousStatus: 'submitted',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('insp-1', $event->inspectionId);
    self::assertSame('eq-1', $event->equipmentId);
    self::assertSame('submitted', $event->previousStatus);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
}
