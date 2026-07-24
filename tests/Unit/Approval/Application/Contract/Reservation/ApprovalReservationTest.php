<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\Contract\Reservation;

use Approval\Application\Contract\Reservation\ApprovalReservation;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ApprovalReservation.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalReservation::class)]
final class ApprovalReservationTest extends TestCase
{
  #[Test]
  public function testExposesConstructorArguments(): void
  {
    $reservation = new ApprovalReservation(id: 'req-1', isNew: true);

    self::assertSame('req-1', $reservation->id);
    self::assertTrue($reservation->isNew);
  }

  #[Test]
  public function testReusedReservationIsNotNew(): void
  {
    $reservation = new ApprovalReservation(id: 'req-2', isNew: false);

    self::assertSame('req-2', $reservation->id);
    self::assertFalse($reservation->isNew);
  }
}
