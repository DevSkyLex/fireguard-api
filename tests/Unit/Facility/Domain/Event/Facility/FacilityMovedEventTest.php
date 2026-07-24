<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Event\Facility;

use DateTimeImmutable;
use Facility\Domain\Event\Facility\FacilityMovedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityMovedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityMovedEvent::class)]
final class FacilityMovedEventTest extends TestCase
{
  #[Test]
  public function testConstructorExposesPayload(): void
  {
    $before = new DateTimeImmutable();
    $event = new FacilityMovedEvent(
      organizationId: 'org-1',
      facilityId: 'fac-1',
      previousParentFacilityId: 'parent-old',
      newParentFacilityId: 'parent-new',
    );
    $after = new DateTimeImmutable();

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('fac-1', $event->facilityId);
    self::assertSame('parent-old', $event->previousParentFacilityId);
    self::assertSame('parent-new', $event->newParentFacilityId);
    self::assertGreaterThanOrEqual($before->getTimestamp(), $event->occurredAt->getTimestamp());
    self::assertLessThanOrEqual($after->getTimestamp(), $event->occurredAt->getTimestamp());
  }

  #[Test]
  public function testConstructorAcceptsNullParentsForRootMove(): void
  {
    $event = new FacilityMovedEvent(
      organizationId: 'org-1',
      facilityId: 'fac-1',
      previousParentFacilityId: null,
      newParentFacilityId: null,
    );

    self::assertNull($event->previousParentFacilityId);
    self::assertNull($event->newParentFacilityId);
  }
}
