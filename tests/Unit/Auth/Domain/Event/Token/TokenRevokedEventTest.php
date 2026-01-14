<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Event\Token;

use Auth\Domain\Event\Token\TokenRevokedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test TokenRevokedEventTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TokenRevokedEvent::class)]
final class TokenRevokedEventTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeCreated.
   */
  #[Test]
  public function testCanBeCreated(): void
  {
    $before = new DateTimeImmutable();
    $event = new TokenRevokedEvent(
      tokenId: 'token-abc',
      tokenType: 'access_token',
      reason: 'user_logout',
    );
    $after = new DateTimeImmutable();

    $this->assertSame('token-abc', $event->tokenId);
    $this->assertSame('access_token', $event->tokenType);
    $this->assertSame('user_logout', $event->reason);
    $this->assertGreaterThanOrEqual($before, $event->occurredAt);
    $this->assertLessThanOrEqual($after, $event->occurredAt);
  }
  // #endregion
}
