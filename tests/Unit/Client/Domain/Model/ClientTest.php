<?php

declare(strict_types=1);

namespace Tests\Client\Domain\Model;

use Client\Domain\Event\{
  ClientActivatedEvent,
  ClientDeactivatedEvent,
  ClientDeletedEvent,
  ClientRegisteredEvent,
  ClientSecretRegeneratedEvent,
  ClientUpdatedEvent
};
use Client\Domain\Model\Client;
use Client\Domain\ValueObject\{
  ClientId,
  ClientName,
  ClientSecret
};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\ValueObject\{
  GrantType,
  GrantTypes,
  RedirectUri,
  Scope,
  Scopes
};
use Tests\Helper\TestEventIdProvider;

/**
 * Test ClientTest
 * @final
 *
 * Test class for the Client domain model.
 *
 * @category Model Tests
 * @package Tests\Client\Domain\Model
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: Client::class)]
final class ClientTest extends TestCase
{
  //#region Methods
  /**
   * Method testRegisterCreatesNewClient
   *
   * Test that register creates a new
   * client with correct properties
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testRegisterCreatesNewClient(): void
  {
    $clientId = new ClientId(value: '123e4567-e89b-12d3-a456-426614174000');
    $clientName = new ClientName(value: 'Test Client');
    $hashedSecret = password_hash('secret', PASSWORD_BCRYPT);
    $clientSecret = new ClientSecret(value: $hashedSecret);
    $redirectUris = [new RedirectUri(value: 'https://example.com/callback')];
    $grantTypes = new GrantTypes(GrantType::AUTHORIZATION_CODE);
    $scopes = new Scopes(Scope::READ);

    $client = Client::register(
      id: $clientId,
      name: $clientName,
      secret: $clientSecret,
      redirectUris: $redirectUris,
      grantTypes: $grantTypes,
      scopes: $scopes,
      eventIdProvider: new TestEventIdProvider(),
    );

    self::assertSame(expected: $clientId, actual: $client->id());
    self::assertSame(expected: $clientName, actual: $client->name());
    self::assertSame(expected: $clientSecret, actual: $client->secret());
    self::assertTrue(condition: $client->isActive());
    self::assertFalse(condition: $client->isDeleted());
  }

  /**
   * Method testRegisterRecordsClientRegisteredEvent
   *
   * Test that register records a
   * ClientRegisteredEvent
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testRegisterRecordsClientRegisteredEvent(): void
  {
    $client = $this->createTestClient();

    $events = $client->releaseEvents();

    self::assertCount(expectedCount: 1, haystack: $events);
    self::assertInstanceOf(expected: ClientRegisteredEvent::class, actual: $events[0]);
  }

  /**
   * Method testUpdateDetailsChangesClientProperties
   *
   * Test that updateDetails changes
   * client properties correctly
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testUpdateDetailsChangesClientProperties(): void
  {
    $client = $this->createTestClient();
    $client->releaseEvents(); // Clear registration event

    $newName = new ClientName(value: 'Updated Client');
    $newRedirectUris = [new RedirectUri(value: 'https://new.example.com/callback')];
    $newScopes = new Scopes(Scope::WRITE);

    $client->updateDetails(
      name: $newName,
      redirectUris: $newRedirectUris,
      scopes: $newScopes,
      eventIdProvider: new TestEventIdProvider(),
    );

    self::assertSame(expected: $newName, actual: $client->name());
    $events = $client->releaseEvents();
    self::assertCount(expectedCount: 1, haystack: $events);
    self::assertInstanceOf(expected: ClientUpdatedEvent::class, actual: $events[0]);
  }

  /**
   * Method testRegenerateSecretChangesSecret
   *
   * Test that regenerateSecret changes
   * the client secret
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testRegenerateSecretChangesSecret(): void
  {
    $client = $this->createTestClient();
    $oldSecret = $client->secret();
    $client->releaseEvents();

    $newHashedSecret = password_hash('new-secret', PASSWORD_BCRYPT);
    $newSecret = new ClientSecret(value: $newHashedSecret);

    $client->regenerateSecret(newSecret: $newSecret, eventIdProvider: new TestEventIdProvider());

    self::assertNotSame(expected: $oldSecret, actual: $client->secret());
    self::assertSame(expected: $newSecret, actual: $client->secret());
    $events = $client->releaseEvents();
    self::assertCount(expectedCount: 1, haystack: $events);
    self::assertInstanceOf(expected: ClientSecretRegeneratedEvent::class, actual: $events[0]);
  }

  /**
   * Method testActivateSetsClientAsActive
   *
   * Test that activate sets the
   * client as active
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testActivateSetsClientAsActive(): void
  {
    $client = $this->createTestClient();
    $client->deactivate(new TestEventIdProvider());
    $client->releaseEvents();

    $client->activate(new TestEventIdProvider());

    self::assertTrue(condition: $client->isActive());
    $events = $client->releaseEvents();
    self::assertCount(expectedCount: 1, haystack: $events);
    self::assertInstanceOf(expected: ClientActivatedEvent::class, actual: $events[0]);
  }

  /**
   * Method testDeactivateSetsClientAsInactive
   *
   * Test that deactivate sets the
   * client as inactive
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testDeactivateSetsClientAsInactive(): void
  {
    $client = $this->createTestClient();
    $client->releaseEvents();

    $client->deactivate(new TestEventIdProvider());

    self::assertFalse(condition: $client->isActive());
    $events = $client->releaseEvents();
    self::assertCount(expectedCount: 1, haystack: $events);
    self::assertInstanceOf(expected: ClientDeactivatedEvent::class, actual: $events[0]);
  }

  /**
   * Method testDeleteMarksClientAsDeleted
   *
   * Test that delete marks the
   * client as deleted (soft delete)
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testDeleteMarksClientAsDeleted(): void
  {
    $client = $this->createTestClient();
    $client->releaseEvents();

    $client->delete(new TestEventIdProvider());

    self::assertTrue(condition: $client->isDeleted());
    $events = $client->releaseEvents();
    self::assertCount(expectedCount: 1, haystack: $events);
    self::assertInstanceOf(expected: ClientDeletedEvent::class, actual: $events[0]);
  }

  /**
   * Method testValidateRedirectUriReturnsTrueForAllowedUri
   *
   * Test that validateRedirectUri returns
   * true for an allowed URI
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testValidateRedirectUriReturnsTrueForAllowedUri(): void
  {
    $client = $this->createTestClient();
    $uri = new RedirectUri(value: 'https://example.com/callback');

    self::assertTrue(condition: $client->validateRedirectUri(uri: $uri));
  }

  /**
   * Method testValidateRedirectUriReturnsFalseForDisallowedUri
   *
   * Test that validateRedirectUri returns
   * false for a disallowed URI
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testValidateRedirectUriReturnsFalseForDisallowedUri(): void
  {
    $client = $this->createTestClient();
    $uri = new RedirectUri(value: 'https://evil.com/callback');

    self::assertFalse(condition: $client->validateRedirectUri(uri: $uri));
  }

  /**
   * Method testSupportsGrantTypeReturnsTrueForSupportedType
   *
   * Test that supportsGrantType returns
   * true for a supported grant type
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testSupportsGrantTypeReturnsTrueForSupportedType(): void
  {
    $client = $this->createTestClient();
    $grantType = GrantType::AUTHORIZATION_CODE;

    self::assertTrue(condition: $client->supportsGrantType(grantType: $grantType));
  }

  /**
   * Method testHasScopeReturnsTrueForAllowedScope
   *
   * Test that hasScope returns true
   * for an allowed scope
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testHasScopeReturnsTrueForAllowedScope(): void
  {
    $client = $this->createTestClient();
    $scope = Scope::READ;

    self::assertTrue(condition: $client->hasScope(scope: $scope));
  }

  /**
   * Method createTestClient
   *
   * Helper method to create a test client
   *
   * @access private
   *
   * @return Client Test client instance
   */
  private function createTestClient(): Client
  {
    $hashedSecret = password_hash('test-secret', PASSWORD_BCRYPT);

    return Client::register(
      id: new ClientId(value: '123e4567-e89b-12d3-a456-426614174000'),
      name: new ClientName(value: 'Test Client'),
      secret: new ClientSecret(value: $hashedSecret),
      redirectUris: [new RedirectUri(value: 'https://example.com/callback')],
      grantTypes: new GrantTypes(GrantType::AUTHORIZATION_CODE),
      scopes: new Scopes(Scope::READ),
      eventIdProvider: new TestEventIdProvider(),
    );
  }
  //#endregion
}

