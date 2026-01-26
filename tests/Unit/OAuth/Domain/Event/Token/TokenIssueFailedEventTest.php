<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Event\Token;

use DateTimeImmutable;
use OAuth\Domain\Event\Token\TokenIssueFailedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class TokenIssueFailedEventTest.
 *
 * Unit tests for the TokenIssueFailedEvent.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TokenIssueFailedEvent::class)]
final class TokenIssueFailedEventTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeCreated.
   */
  #[Test]
  public function testCanBeCreated(): void
  {
    $event = new TokenIssueFailedEvent(
      grantType: 'client_credentials',
      clientId: 'client-123',
      ipAddress: '127.0.0.1',
      reason: 'invalid_client',
    );

    $this->assertSame('client_credentials', $event->grantType);
    $this->assertSame('client-123', $event->clientId);
    $this->assertSame('127.0.0.1', $event->ipAddress);
    $this->assertSame('invalid_client', $event->reason);
    $this->assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
  // #endregion
}
