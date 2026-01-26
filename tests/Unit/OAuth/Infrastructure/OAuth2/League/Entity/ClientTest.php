<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\OAuth2\League\Entity;

use OAuth\Infrastructure\OAuth2\League\Entity\Client;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ClientTest.
 *
 * @category Entity Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Client::class)]
final class ClientTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testConstructorSetsProperties(): void
  {
    $client = new Client(
      identifier: 'client-123',
      name: 'My Client',
      redirectUri: 'https://client.example.com/callback',
      isConfidential: false,
    );

    self::assertSame('client-123', $client->getIdentifier());
    self::assertSame('My Client', $client->getName());
    self::assertSame('https://client.example.com/callback', $client->getRedirectUri());
    self::assertFalse($client->isConfidential());
  }
  // #endregion
}
