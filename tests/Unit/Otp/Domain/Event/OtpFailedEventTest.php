<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\Event;

use DateTimeImmutable;
use Otp\Domain\Event\OtpFailedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test OtpFailedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpFailedEvent::class)]
final class OtpFailedEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testEventPayload(): void
  {
    $event = new OtpFailedEvent(
      otpId: 'otp-2',
      userId: 'user-2',
      purpose: 'login',
      attemptsRemaining: 2,
    );

    self::assertInstanceOf(Uuid::class, $event->eventId());
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt());
    self::assertSame('otp-2', $event->aggregateId());
    self::assertSame('Otp', $event->aggregateType());

    $payload = $event->payload();
    self::assertSame(2, $payload['attemptsRemaining']);
  }
  // #endregion
}
