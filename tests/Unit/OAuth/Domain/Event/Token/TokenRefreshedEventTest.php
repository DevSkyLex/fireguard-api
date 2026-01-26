<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Event\Token;

use DateTimeImmutable;
use OAuth\Domain\Event\Token\TokenRefreshedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class TokenRefreshedEventTest.
 *
 * Unit tests for the TokenRefreshedEvent.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TokenRefreshedEvent::class)]
final class TokenRefreshedEventTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeCreated.
   */
  #[Test]
  public function testCanBeCreated(): void
  {
    $event = new TokenRefreshedEvent(
      userId: 'user-123',
      ipAddress: '127.0.0.1',
    );

    $this->assertSame('user-123', $event->userId);
    $this->assertSame('127.0.0.1', $event->ipAddress);
    $this->assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
  // #endregion
}
