<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Event\Consent;

use DateTimeImmutable;
use OAuth\Domain\Event\Consent\ConsentGrantedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ConsentGrantedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConsentGrantedEvent::class)]
final class ConsentGrantedEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testConstructSetsOccurredAt(): void
  {
    $event = new ConsentGrantedEvent(
      userId: 'user-1',
      clientId: 'client-1',
      scopes: ['openid', 'profile'],
      isNew: true,
    );

    self::assertSame('user-1', $event->userId);
    self::assertSame('client-1', $event->clientId);
    self::assertSame(['openid', 'profile'], $event->scopes);
    self::assertTrue($event->isNew);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
  // #endregion
}
