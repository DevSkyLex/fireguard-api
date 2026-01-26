<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\Event;

use DateTimeImmutable;
use Otp\Domain\Event\OtpGeneratedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test OtpGeneratedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpGeneratedEvent::class)]
final class OtpGeneratedEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testEventPayload(): void
  {
    $event = new OtpGeneratedEvent(
      otpId: 'otp-1',
      userId: 'user-1',
      purpose: 'login',
      channel: 'email',
    );

    self::assertInstanceOf(Uuid::class, $event->eventId());
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt());
    self::assertSame('otp-1', $event->aggregateId());
    self::assertSame('Otp', $event->aggregateType());

    $payload = $event->payload();
    self::assertSame('user-1', $payload['userId']);
    self::assertSame('login', $payload['purpose']);
  }
  // #endregion
}
