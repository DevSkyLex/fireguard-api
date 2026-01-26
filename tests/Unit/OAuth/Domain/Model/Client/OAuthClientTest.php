<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Model\Client;

use OAuth\Domain\Model\Client\OAuthClient;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scope;
use OAuth\Domain\ValueObject\Security\GrantType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OAuthClientTest.
 *
 * @category Domain Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OAuthClient::class)]
final class OAuthClientTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testValidations(): void
  {
    $client = new OAuthClient(
      identifier: new OAuthClientIdentifier('client-1'),
      name: 'Test Client',
      redirectUris: ['https://example.com/callback'],
      grantTypes: [GrantType::AUTHORIZATION_CODE],
      scopes: [Scope::READ],
      secret: 'secret',
      isConfidential: true,
    );

    self::assertSame('client-1', $client->identifier()->value);
    self::assertSame('Test Client', $client->name());
    self::assertSame(['https://example.com/callback'], $client->redirectUris());
    self::assertSame([GrantType::AUTHORIZATION_CODE], $client->grantTypes());
    self::assertSame([Scope::READ], $client->scopes());
    self::assertSame('secret', $client->secret());
    self::assertTrue($client->isConfidential());

    self::assertTrue($client->validateRedirectUri('https://example.com/callback'));
    self::assertFalse($client->validateRedirectUri('https://other.com/callback'));
    self::assertTrue($client->supportsGrantType(GrantType::AUTHORIZATION_CODE));
    self::assertFalse($client->supportsGrantType(GrantType::CLIENT_CREDENTIALS));
    self::assertTrue($client->hasScope(Scope::READ));
    self::assertFalse($client->hasScope(Scope::WRITE));
  }

  #[Test]
  public function testPublicClientDefaults(): void
  {
    $client = new OAuthClient(
      identifier: new OAuthClientIdentifier('client-2'),
      name: 'Public Client',
      redirectUris: [],
      grantTypes: [],
      scopes: [],
      secret: null,
      isConfidential: false,
    );

    self::assertNull($client->secret());
    self::assertFalse($client->isConfidential());
  }
  // #endregion
}
