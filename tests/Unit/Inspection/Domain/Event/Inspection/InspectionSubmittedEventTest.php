<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\Event\Inspection;

use DateTimeImmutable;
use Inspection\Domain\Event\Inspection\InspectionSubmittedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InspectionSubmittedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionSubmittedEvent::class)]
final class InspectionSubmittedEventTest extends TestCase
{
  #[Test]
  public function itExposesItsPayloadAndOccurredAt(): void
  {
    $event = new InspectionSubmittedEvent(
      organizationId: 'org-1',
      inspectionId: 'insp-1',
      equipmentId: 'eq-1',
      result: 'pass',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('insp-1', $event->inspectionId);
    self::assertSame('eq-1', $event->equipmentId);
    self::assertSame('pass', $event->result);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
}
