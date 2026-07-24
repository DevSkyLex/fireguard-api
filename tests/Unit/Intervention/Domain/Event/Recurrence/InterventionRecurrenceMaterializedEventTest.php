<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Event\Recurrence;

use DateTimeImmutable;
use Intervention\Domain\Event\Recurrence\InterventionRecurrenceMaterializedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionRecurrenceMaterializedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionRecurrenceMaterializedEvent::class)]
final class InterventionRecurrenceMaterializedEventTest extends TestCase
{
  #[Test]
  public function testSuccessfulMaterializationCarriesTheCreatedInterventionId(): void
  {
    $before = new DateTimeImmutable();

    $event = new InterventionRecurrenceMaterializedEvent('org-1', 'recurrence-2', true, 'intervention-3');

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('recurrence-2', $event->recurrenceId);
    self::assertTrue($event->succeeded);
    self::assertSame('intervention-3', $event->interventionId);
    self::assertNull($event->error);
    self::assertGreaterThanOrEqual($before, $event->occurredAt);
  }

  #[Test]
  public function testFailedMaterializationCarriesTheError(): void
  {
    $event = new InterventionRecurrenceMaterializedEvent('org-1', 'recurrence-2', false, null, 'template missing');

    self::assertFalse($event->succeeded);
    self::assertNull($event->interventionId);
    self::assertSame('template missing', $event->error);
  }

  #[Test]
  public function testOptionalFieldsDefaultToNull(): void
  {
    $event = new InterventionRecurrenceMaterializedEvent('org-1', 'recurrence-2', true);

    self::assertNull($event->interventionId);
    self::assertNull($event->error);
  }
}
