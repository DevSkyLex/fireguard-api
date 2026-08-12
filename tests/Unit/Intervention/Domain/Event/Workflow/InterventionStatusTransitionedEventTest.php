<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Event\Workflow;

use DateTimeImmutable;
use Intervention\Domain\Event\Workflow\InterventionStatusTransitionedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionStatusTransitionedEventTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionStatusTransitionedEvent::class)]
final class InterventionStatusTransitionedEventTest extends TestCase
{
  #[Test]
  public function testExposesItsPayloadAndStampsOccurredAt(): void
  {
    $before = new DateTimeImmutable();

    $event = new InterventionStatusTransitionedEvent(
      organizationId: 'org-1',
      interventionId: 'intervention-2',
      interventionNumber: 42,
      actorUserId: 'user-3',
      fromStatus: 'planned',
      toStatus: 'in_progress',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('intervention-2', $event->interventionId);
    self::assertSame(42, $event->interventionNumber);
    self::assertSame('user-3', $event->actorUserId);
    self::assertSame('planned', $event->fromStatus);
    self::assertSame('in_progress', $event->toStatus);
    self::assertNull($event->reviewNote);
    self::assertGreaterThanOrEqual($before, $event->occurredAt);
  }

  #[Test]
  public function testCarriesTheReviewNoteForAChangesRequestedTarget(): void
  {
    $event = new InterventionStatusTransitionedEvent(
      organizationId: 'org-1',
      interventionId: 'intervention-2',
      interventionNumber: 42,
      actorUserId: 'user-3',
      fromStatus: 'submitted',
      toStatus: 'changes_requested',
      reviewNote: 'Please redo the panel check.',
    );

    self::assertSame('Please redo the panel check.', $event->reviewNote);
  }

  #[Test]
  public function testActorUserIdMayBeNullForASystemDrivenTransition(): void
  {
    $event = new InterventionStatusTransitionedEvent(
      organizationId: 'org-1',
      interventionId: 'intervention-2',
      interventionNumber: 42,
      actorUserId: null,
      fromStatus: 'planned',
      toStatus: 'in_progress',
    );

    self::assertNull($event->actorUserId);
  }
}
