<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Event;

use OAuth\Domain\Event\TokenIssuedEvent;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class TokenIssuedEventTest
 *
 * Unit tests for the TokenIssuedEvent.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Domain\Event
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \OAuth\Domain\Event\TokenIssuedEvent
 */
#[CoversClass(className: TokenIssuedEvent::class)]
final class TokenIssuedEventTest extends TestCase
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
    $event = new TokenIssuedEvent(
      tokenId: 'token-123',
      grantType: 'password',
      clientId: 'client-456',
      userId: 'user-789',
      scopes: ['read', 'write'],
      expiresIn: 3600,
    );

    $this->assertEquals(expected: 'token-123', actual: $event->tokenId);
    $this->assertEquals(expected: 'password', actual: $event->grantType);
    $this->assertEquals(expected: 'client-456', actual: $event->clientId);
    $this->assertEquals(expected: 'user-789', actual: $event->userId);
    $this->assertEquals(expected: ['read', 'write'], actual: $event->scopes);
    $this->assertEquals(expected: 3600, actual: $event->expiresIn);
    $this->assertInstanceOf(expected: \DateTimeImmutable::class, actual: $event->occurredAt);
  }

  /**
   * Method testCanBeCreatedWithNullUserId
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanBeCreatedWithNullUserId(): void
  {
    $event = new TokenIssuedEvent(
      tokenId: 'token-123',
      grantType: 'client_credentials',
      clientId: 'client-456',
      userId: null,
      scopes: ['read'],
      expiresIn: 3600,
    );

    $this->assertNull(actual: $event->userId);
    $this->assertEquals(expected: 'client_credentials', actual: $event->grantType);
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
    $event = new TokenIssuedEvent(
      tokenId: 'token-123',
      grantType: 'password',
      clientId: 'client-456',
      userId: 'user-789',
      scopes: ['read'],
      expiresIn: 3600,
    );
    $after = new \DateTimeImmutable();

    $this->assertGreaterThanOrEqual($before, $event->occurredAt);
    $this->assertLessThanOrEqual($after, $event->occurredAt);
  }
  //#endregion
}
