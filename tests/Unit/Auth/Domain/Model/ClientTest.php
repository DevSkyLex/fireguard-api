<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Model;

use Auth\Domain\Model\Client;
use PHPUnit\Framework\Attributes\{
  CoversClass,
  Test
};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\{
  GrantType,
  HashedSecret,
  OAuthClientIdentifier,
  Scope
};

/**
 * Class ClientTest
 *
 * Unit tests for the Client entity.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Domain\Model
 * 
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Auth\Domain\Model\Client
 */
#[CoversClass(className: Client::class)]
final class ClientTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanCreateClient
   *
   * Tests that a Client can be created
   * with valid values.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  #[Test]
  public function testCanCreateClient(): void
  {
    $identifier = new OAuthClientIdentifier(value: 'client_id');
    $name = 'Test Client';
    $redirectUris = ['https://example.com/callback'];

    /** 
     * List of grant types
     * @var list<GrantType> $grantTypes 
     */
    $grantTypes = [GrantType::AUTHORIZATION_CODE];

    /** 
     * List of scopes
     * @var list<Scope> $scopes 
     */
    $scopes = [Scope::READ, Scope::WRITE];
    
    $secret = new HashedSecret(value: '$hashed_secret');

    $client = new Client(
      identifier: $identifier,
      name: $name,
      redirectUris: $redirectUris,
      grantTypes: [GrantType::AUTHORIZATION_CODE],
      scopes: [Scope::READ, Scope::WRITE],
      secret: $secret,
      isConfidential: true
    );

    $this->assertEquals(
      expected: $identifier,
      actual: $client->identifier()
    );

    $this->assertEquals(
      expected: $name,
      actual: $client->name()
    );

    $this->assertEquals(
      expected: $redirectUris,
      actual: $client->redirectUris()
    );

    $this->assertEquals(
      expected: $grantTypes,
      actual: $client->grantTypes()
    );

    $this->assertEquals(
      expected: $scopes,
      actual: $client->scopes()
    );

    $this->assertEquals(
      expected: $secret,
      actual: $client->secret()
    );

    $this->assertTrue(condition: $client->isConfidential());
  }

  /**
   * Method testValidateRedirectUri
   *
   * Tests that validateRedirectUri correctly
   * validates redirect URIs.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  #[Test]
  public function testValidateRedirectUri(): void
  {
    $client = new Client(
      identifier: new OAuthClientIdentifier('client_id'),
      name: 'Test Client',
      redirectUris: ['https://example.com/callback'],
      grantTypes: [GrantType::AUTHORIZATION_CODE],
      scopes: [Scope::READ]
    );

    $this->assertTrue(condition: $client->validateRedirectUri(
      uri: 'https://example.com/callback'
    ));

    $this->assertFalse(condition: $client->validateRedirectUri(
      uri: 'https://attacker.com/callback'
    ));
  }

  /**
   * Method testSupportsGrantType
   *
   * Tests that supportsGrantType correctly
   * checks for supported grant types.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  #[Test]
  public function testSupportsGrantType(): void
  {
    $client = new Client(
      identifier: new OAuthClientIdentifier(value: 'client_id'),
      name: 'Test Client',
      redirectUris: ['https://example.com/callback'],
      grantTypes: [GrantType::AUTHORIZATION_CODE],
      scopes: [Scope::READ]
    );

    $this->assertTrue(condition: $client->supportsGrantType(
      grantType: GrantType::AUTHORIZATION_CODE
    ));

    $this->assertFalse(condition: $client->supportsGrantType(
      grantType: GrantType::CLIENT_CREDENTIALS
    ));
  }

  /**
   * Method testHasScope
   *
   * Tests that hasScope correctly checks
   * for assigned scopes.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  #[Test]
  public function testHasScope(): void
  {
    $client = new Client(
      identifier: new OAuthClientIdentifier(value: 'client_id'),
      name: 'Test Client',
      redirectUris: ['https://example.com/callback'],
      grantTypes: [GrantType::AUTHORIZATION_CODE],
      scopes: [Scope::READ, Scope::WRITE]
    );

    $this->assertTrue(condition: $client->hasScope(
      scope: Scope::READ
    ));

    $this->assertTrue(condition: $client->hasScope(
      scope: Scope::WRITE
    ));

    $this->assertFalse(condition: $client->hasScope(
      scope: Scope::DELETE
    ));
  }
  //#endregion
}
