<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Service;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Infrastructure\Service\UuidEventIdProvider;

/**
 * Test UuidEventIdProviderTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UuidEventIdProvider::class)]
final class UuidEventIdProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testNextEventIdReturnsUuidValueObject(): void
  {
    $generator = $this->createMock(UuidGeneratorPort::class);
    $generator->expects(self::once())
      ->method('generate')
      ->willReturn('00000000-0000-4000-a000-000000000001');

    $provider = new UuidEventIdProvider($generator);

    $eventId = $provider->nextEventId();

    self::assertSame('00000000-0000-4000-a000-000000000001', $eventId->value);
  }
  // #endregion
}
