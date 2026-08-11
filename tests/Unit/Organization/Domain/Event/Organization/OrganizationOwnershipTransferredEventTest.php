<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Event\Organization;

use DateTimeImmutable;
use Organization\Domain\Event\Organization\OrganizationOwnershipTransferredEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationOwnershipTransferredEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationOwnershipTransferredEvent::class)]
final class OrganizationOwnershipTransferredEventTest extends TestCase
{
  #[Test]
  public function testExposesPayload(): void
  {
    $event = new OrganizationOwnershipTransferredEvent('org-1', 'previous-owner', 'new-owner');

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('previous-owner', $event->previousOwnerUserId);
    self::assertSame('new-owner', $event->newOwnerUserId);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
}
