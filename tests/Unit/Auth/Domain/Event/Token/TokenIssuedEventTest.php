<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Event\Token;

use Auth\Domain\Event\Token\TokenIssuedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test TokenIssuedEventTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TokenIssuedEvent::class)]
final class TokenIssuedEventTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeCreated.
   */
  #[Test]
  public function testCanBeCreated(): void
  {
    $before = new DateTimeImmutable();
    $event = new TokenIssuedEvent(
      tokenId: 'token-123',
      grantType: 'password',
      clientId: 'client-123',
      userId: 'user-123',
      scopes: ['READ', 'WRITE'],
      expiresIn: 3600,
      ipAddress: '127.0.0.1',
    );
    $after = new DateTimeImmutable();

    $this->assertSame('token-123', $event->tokenId);
    $this->assertSame('password', $event->grantType);
    $this->assertSame('client-123', $event->clientId);
    $this->assertSame('user-123', $event->userId);
    $this->assertSame(['READ', 'WRITE'], $event->scopes);
    $this->assertSame(3600, $event->expiresIn);
    $this->assertSame('127.0.0.1', $event->ipAddress);
    $this->assertGreaterThanOrEqual($before, $event->occurredAt);
    $this->assertLessThanOrEqual($after, $event->occurredAt);
  }
  // #endregion
}
