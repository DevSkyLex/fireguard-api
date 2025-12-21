<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use OAuth\Domain\Model\Client;
use OAuth\Domain\ValueObject\ClientId;
use OAuth\Domain\ValueObject\ClientName;
use OAuth\Domain\ValueObject\ClientSecret;
use OAuth\Domain\ValueObject\GrantType;
use OAuth\Domain\ValueObject\GrantTypes;
use OAuth\Domain\ValueObject\RedirectUri;
use OAuth\Domain\ValueObject\Scope;
use OAuth\Domain\ValueObject\Scopes;
use OAuth\Infrastructure\Persistence\Doctrine\Mapper\ClientMapper;
use Shared\Infrastructure\Service\UuidEventIdProvider;
use Shared\Infrastructure\Symfony\Adapter\Outbound\UuidGeneratorAdapter;

use function array_map;
use function password_hash;

use const PASSWORD_BCRYPT;

/**
 * Fixtures ClientFixtures.
 *
 * Loads sample OAuth2 clients into the database.
 *
 * @category DataFixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class ClientFixtures extends Fixture implements FixtureGroupInterface
{
  // #region Constants
  /**
   * Constant WEB_CLIENT_REFERENCE.
   *
   * Reference for the web client
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string WEB_CLIENT_REFERENCE = 'web-client';

  /**
   * Constant MOBILE_CLIENT_REFERENCE.
   *
   * Reference for the mobile client
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string MOBILE_CLIENT_REFERENCE = 'mobile-client';

  /**
   * Constant API_CLIENT_REFERENCE.
   *
   * Reference for the api client
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string API_CLIENT_REFERENCE = 'api-client';

  /**
   * Constant DEV_CLIENT_REFERENCE.
   *
   * Reference for the dev client
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string DEV_CLIENT_REFERENCE = 'dev-client';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the client mapper
   *
   * @since 1.0.0
   */
  public function __construct(
    private readonly ClientMapper $clientMapper,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method getGroups.
   *
   * Get the groups
   *
   * @since 1.0.0
   *
   * @return array<string> The groups
   */
  public static function getGroups(): array
  {
    return ['client', 'default'];
  }

  /**
   * Method load.
   *
   * Load the fixtures
   *
   * @since 1.0.0
   *
   * @param ObjectManager $manager The object manager
   *
   * @return void No return value
   */
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
      ],
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
      ],
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
      ],
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
      ],
    );
    $devRecord = $this->clientMapper->toRecord($devClient);
    $manager->persist($devRecord);
    $this->addReference(self::DEV_CLIENT_REFERENCE, $devRecord);

    $manager->flush();
  }

  /**
   * Method createClient.
   *
   * Create a client
   *
   * @since 1.0.0
   *
   * @param string $id The client id
   * @param string $name The client name
   * @param string $secret The client secret
   * @param array<string> $redirectUris The redirect uris
   * @param array<GrantType> $grantTypes The grant types
   * @param array<Scope> $scopes The scopes
   *
   * @return Client The client
   */
  private function createClient(
    string $id,
    string $name,
    string $secret,
    array $redirectUris,
    array $grantTypes,
    array $scopes,
  ): Client {
    $redirectUriObjects = array_map(
      fn (string $uri) => new RedirectUri($uri),
      $redirectUris,
    );

    $client = Client::register(
      id: new ClientId($id),
      name: new ClientName($name),
      secret: new ClientSecret(password_hash($secret, PASSWORD_BCRYPT)),
      redirectUris: $redirectUriObjects,
      grantTypes: new GrantTypes(...$grantTypes),
      scopes: new Scopes(...$scopes),
      eventIdProvider: new UuidEventIdProvider(new UuidGeneratorAdapter()),
    );

    // Clear domain events
    $client->releaseEvents();

    return $client;
  }
  // #endregion
}
