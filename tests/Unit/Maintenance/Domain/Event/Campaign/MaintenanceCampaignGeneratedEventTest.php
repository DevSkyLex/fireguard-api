<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\Event\Campaign;

use DateTimeImmutable;
use Maintenance\Domain\Event\Campaign\MaintenanceCampaignGeneratedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceCampaignGeneratedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceCampaignGeneratedEvent::class)]
final class MaintenanceCampaignGeneratedEventTest extends TestCase
{
  #[Test]
  public function testExposesItsPayload(): void
  {
    $before = new DateTimeImmutable();
    $event = new MaintenanceCampaignGeneratedEvent('org-1', 'intervention-1', 7, 'user-1');
    $after = new DateTimeImmutable();

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('intervention-1', $event->interventionId);
    self::assertSame(7, $event->workItemsCount);
    self::assertSame('user-1', $event->actorUserId);
    self::assertGreaterThanOrEqual($before, $event->occurredAt);
    self::assertLessThanOrEqual($after, $event->occurredAt);
  }

  #[Test]
  public function testActorUserIdDefaultsToNull(): void
  {
    $event = new MaintenanceCampaignGeneratedEvent('org-1', 'intervention-1', 0);

    self::assertNull($event->actorUserId);
    self::assertSame(0, $event->workItemsCount);
  }
}
