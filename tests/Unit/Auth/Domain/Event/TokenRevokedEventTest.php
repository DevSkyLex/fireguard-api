<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Event;

use Auth\Domain\Event\TokenRevokedEvent;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class TokenRevokedEventTest
 *
 * Unit tests for the TokenRevokedEvent.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Domain\Event
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Domain\Event\TokenRevokedEvent
 */
#[CoversClass(className: TokenRevokedEvent::class)]
final class TokenRevokedEventTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanBeCreated
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanBeCreated(): void
  {
    $event = new TokenRevokedEvent(
      tokenId: 'token-123',
      tokenType: 'access_token',
      reason: 'user_logout',
    );

    $this->assertEquals(expected: 'token-123', actual: $event->tokenId);
    $this->assertEquals(expected: 'access_token', actual: $event->tokenType);
    $this->assertEquals(expected: 'user_logout', actual: $event->reason);
    $this->assertInstanceOf(expected: \DateTimeImmutable::class, actual: $event->occurredAt);
  }

  /**
   * Method testCanBeCreatedWithNullReason
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanBeCreatedWithNullReason(): void
  {
    $event = new TokenRevokedEvent(
      tokenId: 'token-123',
      tokenType: 'refresh_token',
      reason: null,
    );

    $this->assertNull(actual: $event->reason);
    $this->assertEquals(expected: 'refresh_token', actual: $event->tokenType);
  }

  /**
   * Method testOccurredAtIsSetAutomatically
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testOccurredAtIsSetAutomatically(): void
  {
    $before = new \DateTimeImmutable();
    $event = new TokenRevokedEvent(
      tokenId: 'token-123',
      tokenType: 'access_token',
    );
    $after = new \DateTimeImmutable();

    $this->assertGreaterThanOrEqual($before, $event->occurredAt);
    $this->assertLessThanOrEqual($after, $event->occurredAt);
  }
  //#endregion
}
