<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Event\Publication;

use DateTimeImmutable;
use Intervention\Domain\Event\Publication\InterventionPublishedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionPublishedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionPublishedEvent::class)]
final class InterventionPublishedEventTest extends TestCase
{
  #[Test]
  public function testExposesItsPayloadAndStampsOccurredAt(): void
  {
    $before = new DateTimeImmutable();

    $event = new InterventionPublishedEvent('org-1', 'intervention-2', 'publication-3');

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('intervention-2', $event->interventionId);
    self::assertSame('publication-3', $event->publicationId);
    self::assertGreaterThanOrEqual($before, $event->occurredAt);
  }
}
