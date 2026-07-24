<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Event\Recurrence;

use DateTimeImmutable;
use Intervention\Domain\Event\Recurrence\InterventionRecurrenceUpdatedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionRecurrenceUpdatedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionRecurrenceUpdatedEvent::class)]
final class InterventionRecurrenceUpdatedEventTest extends TestCase
{
  #[Test]
  public function testExposesItsPayloadAndStampsOccurredAt(): void
  {
    $before = new DateTimeImmutable();

    $event = new InterventionRecurrenceUpdatedEvent('org-1', 'recurrence-2', 'user-3');

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('recurrence-2', $event->recurrenceId);
    self::assertSame('user-3', $event->actorUserId);
    self::assertGreaterThanOrEqual($before, $event->occurredAt);
  }

  #[Test]
  public function testActorUserIdDefaultsToNull(): void
  {
    $event = new InterventionRecurrenceUpdatedEvent('org-1', 'recurrence-2');

    self::assertNull($event->actorUserId);
  }
}
