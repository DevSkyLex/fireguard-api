<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Event\Publication;

use DateTimeImmutable;
use Intervention\Domain\Event\Publication\InterventionPublicationFailedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionPublicationFailedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionPublicationFailedEvent::class)]
final class InterventionPublicationFailedEventTest extends TestCase
{
  #[Test]
  public function testExposesItsPayloadAndStampsOccurredAt(): void
  {
    $before = new DateTimeImmutable();

    $event = new InterventionPublicationFailedEvent(
      'org-1',
      'intervention-2',
      'publication-3',
      'stale revision',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('intervention-2', $event->interventionId);
    self::assertSame('publication-3', $event->publicationId);
    self::assertSame('stale revision', $event->reason);
    self::assertGreaterThanOrEqual($before, $event->occurredAt);
  }
}
