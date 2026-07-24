<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\Event\NonConformity;

use DateTimeImmutable;
use Inspection\Domain\Event\NonConformity\NonConformityRecordedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test NonConformityRecordedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NonConformityRecordedEvent::class)]
final class NonConformityRecordedEventTest extends TestCase
{
  #[Test]
  public function itExposesItsPayloadAndOccurredAt(): void
  {
    $event = new NonConformityRecordedEvent(
      organizationId: 'org-1',
      inspectionId: 'insp-1',
      nonConformityId: 'nc-1',
      severity: 'critical',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('insp-1', $event->inspectionId);
    self::assertSame('nc-1', $event->nonConformityId);
    self::assertSame('critical', $event->severity);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
}
