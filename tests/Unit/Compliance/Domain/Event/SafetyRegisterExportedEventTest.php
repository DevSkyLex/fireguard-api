<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Domain\Event;

use Compliance\Domain\Event\SafetyRegisterExportedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test SafetyRegisterExportedEventTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SafetyRegisterExportedEvent::class)]
final class SafetyRegisterExportedEventTest extends TestCase
{
  #[Test]
  public function testConstructorExposesPayloadForAnOrganizationWideExport(): void
  {
    $event = new SafetyRegisterExportedEvent(
      organizationId: 'org-1',
      facilityId: null,
      actorUserId: 'user-9',
      planKey: 'pro',
      scope: 'organization',
      generatedAt: '2026-07-24T10:00:00+00:00',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertNull($event->facilityId);
    self::assertSame('user-9', $event->actorUserId);
    self::assertSame('pro', $event->planKey);
    self::assertSame('organization', $event->scope);
    self::assertSame('2026-07-24T10:00:00+00:00', $event->generatedAt);
  }

  #[Test]
  public function testConstructorExposesFacilityIdForAFacilityScopedExport(): void
  {
    $event = new SafetyRegisterExportedEvent(
      organizationId: 'org-1',
      facilityId: 'facility-7',
      actorUserId: 'user-9',
      planKey: 'max',
      scope: 'facility',
      generatedAt: '2026-07-24T10:00:00+00:00',
    );

    self::assertSame('facility-7', $event->facilityId);
    self::assertSame('facility', $event->scope);
  }

  #[Test]
  public function testConstructorStampsOccurredAt(): void
  {
    $before = new DateTimeImmutable();
    $event = new SafetyRegisterExportedEvent('org-1', null, 'user-9', 'pro', 'organization', '2026-07-24T10:00:00+00:00');
    $after = new DateTimeImmutable();

    self::assertGreaterThanOrEqual($before->getTimestamp(), $event->occurredAt->getTimestamp());
    self::assertLessThanOrEqual($after->getTimestamp(), $event->occurredAt->getTimestamp());
  }
}
