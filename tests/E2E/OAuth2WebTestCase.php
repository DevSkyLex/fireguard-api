<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Client\Infrastructure\DataFixtures\ClientFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use User\Infrastructure\DataFixtures\UserFixtures;

/**
 * OAuth2WebTestCase
 *
 * Base test case for OAuth2-authenticated E2E tests.
 * Provides fixture loading and token generation helpers.
 *
 * @category E2E Tests
 * @package App\Tests\E2E
 * @version 1.0.0
 */
abstract class OAuth2WebTestCase extends WebTestCase
{
  protected const string DEV_CLIENT_ID = 'a7b8c9d0-e1f2-4456-8123-789012345678';
  protected const string DEV_CLIENT_SECRET = 'dev_secret_test';
  protected const string API_CLIENT_ID = 'f6a7b8c9-d0e1-4345-8012-678901234567';
  protected const string API_CLIENT_SECRET = 'api_secret_789';

  protected ?string $accessToken = null;

  /**
   * Create client and ensure fixtures are loaded
   */
  protected static function createClientWithFixtures(): KernelBrowser
  {
    $client = static::createClient();
    static::loadTestFixtures($client);
    return $client;
  }

  /**
   * Load fixtures for testing using proper executor
   */
  protected static function loadTestFixtures(KernelBrowser $client): void
  {
    $container = $client->getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.entity_manager');

    // Create schema
    $schemaTool = new SchemaTool($entityManager);
    $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

    try {
      $schemaTool->dropSchema($metadata);
    } catch (\Throwable) {
      // Schema might not exist yet
    }

    $schemaTool->createSchema($metadata);

    // Load fixtures using proper executor with reference repository
    $loader = new Loader();

    /** @var ClientFixtures $clientFixtures */
    $clientFixtures = $container->get(ClientFixtures::class);
    /** @var UserFixtures $userFixtures */
    $userFixtures = $container->get(UserFixtures::class);

    $loader->addFixture($clientFixtures);
    $loader->addFixture($userFixtures);

    $purger = new ORMPurger($entityManager);
    $executor = new ORMExecutor($entityManager, $purger);
    $executor->execute($loader->getFixtures());

    $entityManager->clear();
  }

  /**
   * Get a valid access token for testing
   */
  protected function getAccessToken(KernelBrowser $client): ?string
  {
    if ($this->accessToken !== null) {
      return $this->accessToken;
    }

    $client->request(
      method: 'POST',
      uri: '/api/oauth2/token',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => self::DEV_CLIENT_ID,
        'client_secret' => self::DEV_CLIENT_SECRET,
        'scope' => 'OPENID PROFILE EMAIL READ WRITE',
      ]) ?: ''
    );

    $response = $client->getResponse();

    if ($response->getStatusCode() !== Response::HTTP_OK && $response->getStatusCode() !== Response::HTTP_CREATED) {
      return null;
    }

    $data = json_decode($response->getContent() ?: '{}', true);
    $this->accessToken = $data['access_token'] ?? null;

    return $this->accessToken;
  }
}
