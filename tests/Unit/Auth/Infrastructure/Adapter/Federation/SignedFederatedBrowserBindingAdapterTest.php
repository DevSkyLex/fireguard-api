<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\Federation;

use Auth\Infrastructure\Adapter\Federation\SignedFederatedBrowserBindingAdapter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Infrastructure\Idempotency\HmacIdempotencyFingerprintAdapter;

use function base64_encode;
use function explode;
use function hash;
use function implode;
use function str_repeat;
use function strtr;

/**
 * Test SignedFederatedBrowserBindingAdapterTest
 *
 * @category Test
 */
final class SignedFederatedBrowserBindingAdapterTest extends TestCase
{
  #[Test]
  public function browserTicketsHaveIndependentIdentityAndAbsoluteDeadlines(): void
  {
    $now = new DateTimeImmutable('2026-10-12T00:00:00+00:00');
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturnCallback(static fn (): DateTimeImmutable => $now);
    $adapter = new SignedFederatedBrowserBindingAdapter(new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('b', 32))), $clock);
    $first = $adapter->create();
    $second = $adapter->create();
    self::assertNotSame($first, $second);
    $proof = $adapter->verify($first);
    self::assertNotNull($proof);
    self::assertSame(hash('sha256', $first), $proof->bindingHash);
    self::assertSame($now->getTimestamp() + 600, $proof->expiresAt->getTimestamp());
    self::assertEquals($proof, $adapter->verify($first));
  }

  #[Test]
  public function alteringTheCookieDeadlineCannotExtendBrowserPossession(): void
  {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-12T00:00:00+00:00'));
    $adapter = new SignedFederatedBrowserBindingAdapter(new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('b', 32))), $clock);
    $parts = explode('.', $adapter->create());
    $parts[2] = (string) ((int) $parts[2] + 1);
    self::assertNull($adapter->verify(implode('.', $parts)));
    self::assertNull($adapter->verify('arbitrary-browser-cookie'));
  }

  #[Test]
  public function anotherServerKeyCannotAuthenticateTheTicket(): void
  {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-12T00:00:00+00:00'));
    $first = new SignedFederatedBrowserBindingAdapter(new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('b', 32))), $clock);
    $other = new SignedFederatedBrowserBindingAdapter(new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('c', 32))), $clock);
    self::assertNull($other->verify($first->create()));
  }

  /**
   * Method ticketDeadlineAcceptsOnlyAsciiDigits
   *
   * Keeps the ticket's persisted timestamp alphabet independent of Unicode numeral characters.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function ticketDeadlineAcceptsOnlyAsciiDigits(): void
  {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-12T00:00:00+00:00'));
    $adapter = new SignedFederatedBrowserBindingAdapter(new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('b', 32))), $clock);
    $parts = explode('.', $adapter->create());
    $parts[2] = strtr($parts[2], ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);

    self::assertNull($adapter->verify(implode('.', $parts)));
  }

  #[Test]
  public function ticketExpiresAtItsOriginalDeadline(): void
  {
    $now = new DateTimeImmutable('2026-10-12T00:00:00+00:00');
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturnCallback(static function () use (&$now): DateTimeImmutable { return $now; });
    $adapter = new SignedFederatedBrowserBindingAdapter(new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('b', 32))), $clock);
    $ticket = $adapter->create();
    $now = $now->modify('+600 seconds');
    self::assertNull($adapter->verify($ticket));
  }
}
