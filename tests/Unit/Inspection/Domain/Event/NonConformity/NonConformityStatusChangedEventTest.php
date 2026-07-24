<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\Event\NonConformity;

use DateTimeImmutable;
use Inspection\Domain\Event\NonConformity\NonConformityStatusChangedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test NonConformityStatusChangedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NonConformityStatusChangedEvent::class)]
final class NonConformityStatusChangedEventTest extends TestCase
{
  #[Test]
  public function itExposesItsPayloadAndOccurredAt(): void
  {
    $event = new NonConformityStatusChangedEvent(
      organizationId: 'org-1',
      inspectionId: 'insp-1',
      nonConformityId: 'nc-1',
      previousStatus: 'open',
      status: 'in_progress',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('insp-1', $event->inspectionId);
    self::assertSame('nc-1', $event->nonConformityId);
    self::assertSame('open', $event->previousStatus);
    self::assertSame('in_progress', $event->status);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
}
