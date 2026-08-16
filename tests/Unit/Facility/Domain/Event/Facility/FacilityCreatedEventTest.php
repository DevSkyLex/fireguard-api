<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Event\Facility;

use DateTimeImmutable;
use Facility\Domain\Event\Facility\FacilityCreatedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityCreatedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityCreatedEvent::class)]
final class FacilityCreatedEventTest extends TestCase
{
  #[Test]
  public function testConstructorExposesPayload(): void
  {
    $before = new DateTimeImmutable();
    $event = new FacilityCreatedEvent(
      organizationId: 'org-1',
      facilityId: 'fac-1',
    );
    $after = new DateTimeImmutable();

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('fac-1', $event->facilityId);
    self::assertGreaterThanOrEqual($before->getTimestamp(), $event->occurredAt->getTimestamp());
    self::assertLessThanOrEqual($after->getTimestamp(), $event->occurredAt->getTimestamp());
  }
}
