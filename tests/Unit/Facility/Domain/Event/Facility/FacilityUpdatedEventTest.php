<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Event\Facility;

use DateTimeImmutable;
use Facility\Domain\Event\Facility\FacilityUpdatedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityUpdatedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityUpdatedEvent::class)]
final class FacilityUpdatedEventTest extends TestCase
{
  #[Test]
  public function testConstructorExposesPayload(): void
  {
    $before = new DateTimeImmutable();
    $event = new FacilityUpdatedEvent(
      organizationId: 'org-1',
      facilityId: 'fac-1',
      changedFields: ['name', 'code'],
    );
    $after = new DateTimeImmutable();

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('fac-1', $event->facilityId);
    self::assertSame(['name', 'code'], $event->changedFields);
    self::assertGreaterThanOrEqual($before->getTimestamp(), $event->occurredAt->getTimestamp());
    self::assertLessThanOrEqual($after->getTimestamp(), $event->occurredAt->getTimestamp());
  }

  #[Test]
  public function testConstructorAcceptsEmptyChangedFields(): void
  {
    $event = new FacilityUpdatedEvent(
      organizationId: 'org-1',
      facilityId: 'fac-1',
      changedFields: [],
    );

    self::assertSame([], $event->changedFields);
  }
}
