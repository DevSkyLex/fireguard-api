<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Event\Recurrence;

use DateTimeImmutable;
use Intervention\Domain\Event\Recurrence\InterventionRecurrenceCreatedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionRecurrenceCreatedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionRecurrenceCreatedEvent::class)]
final class InterventionRecurrenceCreatedEventTest extends TestCase
{
  #[Test]
  public function testExposesItsPayloadAndStampsOccurredAt(): void
  {
    $before = new DateTimeImmutable();

    $event = new InterventionRecurrenceCreatedEvent('org-1', 'recurrence-2', 'template-3', 'user-4');

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('recurrence-2', $event->recurrenceId);
    self::assertSame('template-3', $event->templateId);
    self::assertSame('user-4', $event->actorUserId);
    self::assertGreaterThanOrEqual($before, $event->occurredAt);
  }

  #[Test]
  public function testActorUserIdDefaultsToNull(): void
  {
    $event = new InterventionRecurrenceCreatedEvent('org-1', 'recurrence-2', 'template-3');

    self::assertNull($event->actorUserId);
  }
}
