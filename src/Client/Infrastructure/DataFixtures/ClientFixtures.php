<?php

declare(strict_types=1);

namespace Client\Infrastructure\DataFixtures;

use Client\Domain\Model\Client;
use Client\Domain\ValueObject\ClientId;
use Client\Domain\ValueObject\ClientName;
use Client\Domain\ValueObject\ClientSecret;
use Client\Infrastructure\Persistence\Doctrine\Mapper\ClientMapper;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Shared\Domain\ValueObject\GrantType;
use Shared\Domain\ValueObject\GrantTypes;
use Shared\Domain\ValueObject\RedirectUri;
use Shared\Domain\ValueObject\Scope;
use Shared\Domain\ValueObject\Scopes;

/**
 * Fixtures ClientFixtures
 *
 * Loads sample OAuth2 clients into the database.
 *
 * @category DataFixtures
 * @package Client\Infrastructure\DataFixtures
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class ClientFixtures extends Fixture implements FixtureGroupInterface
{
  //#region Constants
  public const string WEB_CLIENT_REFERENCE = 'web-client';
  public const string MOBILE_CLIENT_REFERENCE = 'mobile-client';
  public const string API_CLIENT_REFERENCE = 'api-client';
  public const string DEV_CLIENT_REFERENCE = 'dev-client';

  /**
   * Plain text secrets for testing (DO NOT USE IN PRODUCTION!)
   * - web-client: web_secret_123
   * - mobile-client: mobile_secret_456
   * - api-client: api_secret_789
   * - dev-client: dev_secret_test
   */
  //#endregion

  //#region Constructor
  public function __construct(
    private readonly ClientMapper $clientMapper
  ) {}
  //#endregion

  //#region Methods
  public static function getGroups(): array
  {
    return ['client', 'default'];
  }

  public function load(ObjectManager $manager): void
  {
    // Web Application Client (Authorization Code + PKCE)
    $webClient = $this->createClient(
      id: 'd4e5f6a7-b8c9-4123-8ef0-456789012345',
      name: 'FireGuard Web App',
      secret: 'web_secret_123',
      redirectUris: [
        'https://app.fireguard.local/callback',
        'https://app.fireguard.local/oauth/callback',
      ],
      grantTypes: [
        GrantType::AUTHORIZATION_CODE,
        GrantType::REFRESH_TOKEN,
      ],
      scopes: [
        Scope::OPENID,
        Scope::PROFILE,
        Scope::EMAIL,
        Scope::READ,
        Scope::WRITE,
      ]
    );
    $webRecord = $this->clientMapper->toRecord($webClient);
    $manager->persist($webRecord);
    $this->addReference(self::WEB_CLIENT_REFERENCE, $webRecord);

    // Mobile Application Client
    $mobileClient = $this->createClient(
      id: 'e5f6a7b8-c9d0-4234-8f01-567890123456',
      name: 'FireGuard Mobile App',
      secret: 'mobile_secret_456',
      redirectUris: [
        'https://mobile.fireguard.local/callback',
      ],
      grantTypes: [
        GrantType::AUTHORIZATION_CODE,
        GrantType::REFRESH_TOKEN,
      ],
      scopes: [
        Scope::OPENID,
        Scope::PROFILE,
        Scope::EMAIL,
        Scope::READ,
      ]
    );
    $mobileRecord = $this->clientMapper->toRecord($mobileClient);
    $manager->persist($mobileRecord);
    $this->addReference(self::MOBILE_CLIENT_REFERENCE, $mobileRecord);

    // API/Machine-to-Machine Client (Client Credentials)
    $apiClient = $this->createClient(
      id: 'f6a7b8c9-d0e1-4345-8012-678901234567',
      name: 'FireGuard API Service',
      secret: 'api_secret_789',
      redirectUris: [],
      grantTypes: [
        GrantType::CLIENT_CREDENTIALS,
      ],
      scopes: [
        Scope::READ,
        Scope::WRITE,
      ]
    );
    $apiRecord = $this->clientMapper->toRecord($apiClient);
    $manager->persist($apiRecord);
    $this->addReference(self::API_CLIENT_REFERENCE, $apiRecord);

    // Test/Development Client (All grants)
    $devClient = $this->createClient(
      id: 'a7b8c9d0-e1f2-4456-8123-789012345678',
      name: 'Development Test Client',
      secret: 'dev_secret_test',
      redirectUris: [
        'https://oauth.pstmn.io/v1/callback', // Postman
        'https://oidcdebugger.com/debug', // OIDC Debugger
        'https://localhost:8080/callback',
      ],
      grantTypes: [
        GrantType::AUTHORIZATION_CODE,
        GrantType::CLIENT_CREDENTIALS,
        GrantType::REFRESH_TOKEN,
      ],
      scopes: [
        Scope::OPENID,
        Scope::PROFILE,
        Scope::EMAIL,
        Scope::READ,
        Scope::WRITE,
      ]
    );
    $devRecord = $this->clientMapper->toRecord($devClient);
    $manager->persist($devRecord);
    $this->addReference(self::DEV_CLIENT_REFERENCE, $devRecord);

    $manager->flush();
  }

  /**
   * @param array<string> $redirectUris
   * @param array<GrantType> $grantTypes
   * @param array<Scope> $scopes
   */
  private function createClient(
    string $id,
    string $name,
    string $secret,
    array $redirectUris,
    array $grantTypes,
    array $scopes
  ): Client {
    $redirectUriObjects = array_map(
      fn(string $uri) => new RedirectUri($uri),
      $redirectUris
    );

    $client = Client::register(
      id: new ClientId($id),
      name: new ClientName($name),
      secret: new ClientSecret(password_hash($secret, PASSWORD_BCRYPT)),
      redirectUris: $redirectUriObjects,
      grantTypes: new GrantTypes(...$grantTypes),
      scopes: new Scopes(...$scopes)
    );

    // Clear domain events
    $client->releaseEvents();

    return $client;
  }
  //#endregion
}
