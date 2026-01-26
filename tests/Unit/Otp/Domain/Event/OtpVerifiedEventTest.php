<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\Event;

use DateTimeImmutable;
use Otp\Domain\Event\OtpVerifiedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test OtpVerifiedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpVerifiedEvent::class)]
final class OtpVerifiedEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testEventPayload(): void
  {
    $event = new OtpVerifiedEvent(
      otpId: 'otp-3',
      userId: 'user-3',
      purpose: 'login',
    );

    self::assertInstanceOf(Uuid::class, $event->eventId());
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt());
    self::assertSame('otp-3', $event->aggregateId());
    self::assertSame('Otp', $event->aggregateType());

    $payload = $event->payload();
    self::assertSame('user-3', $payload['userId']);
  }
  // #endregion
}
