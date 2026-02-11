<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Infrastructure\DataFixtures\ClientFixtures;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use User\Infrastructure\DataFixtures\UserFixtures;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function password_verify;
use function uniqid;

/**
 * OAuth2WebTestCase.
 *
 * Base test case for OAuth2-authenticated E2E tests.
 * Provides fixture loading and token generation helpers.
 *
 * @category E2E Tests
 *
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
   * Create client and ensure fixtures are loaded.
   */
  protected static function createClientWithFixtures(): KernelBrowser
  {
    $client = static::createClient();
    $client->disableReboot();
    static::loadTestFixtures($client);

    return $client;
  }

  /**
   * Load fixtures for testing using proper executor.
   */
  protected static function loadTestFixtures(KernelBrowser $client): void
  {
    $container = $client->getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');

    // Load fixtures using proper executor with reference repository
    $loader = new Loader();

    /** @var ClientFixtures $clientFixtures */
    $clientFixtures = $container->get(ClientFixtures::class);
    /** @var UserFixtures $userFixtures */
    $userFixtures = $container->get(UserFixtures::class);

    $loader->addFixture($clientFixtures);
    $loader->addFixture($userFixtures);

    $executor = new ORMExecutor($entityManager);
    // Append mode avoids purge; each test is isolated by transaction rollback.
    $executor->execute($loader->getFixtures(), true);

    $entityManager->clear();
  }

  /**
   * Get a valid access token for testing.
   */
  protected function getAccessToken(KernelBrowser $client): ?string
  {
    if (null !== $this->accessToken) {
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
      ]) ?: '',
    );

    $response = $client->getResponse();

    if (Response::HTTP_OK !== $response->getStatusCode() && Response::HTTP_CREATED !== $response->getStatusCode()) {
      return null;
    }

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $accessToken = $data['access_token'] ?? null;
    $this->accessToken = is_string($accessToken) ? $accessToken : null;

    return $this->accessToken;
  }

  /**
   * Create a user via message bus.
   */
  protected function createUser(KernelBrowser $client, string $email, string $password): void
  {
    $container = static::getContainer();
    /** @var MessageBusInterface $bus */
    $bus = $container->get(MessageBusInterface::class);

    $command = new \User\Application\UseCase\Command\User\CreateUser\CreateUserCommand(
      username: 'user' . uniqid(),
      email: $email,
      password: $password,
      firstName: 'Test',
      lastName: 'User',
    );

    $bus->dispatch($command);
  }

  /**
   * Create and activate a user.
   */
  protected function createAndActivateUser(KernelBrowser $client, string $email, string $password): void
  {
    $this->createUser($client, $email, $password);

    // Activate user directly in database
    $container = static::getContainer();
    /** @var EntityManagerInterface $em */
    $em = $container->get(EntityManagerInterface::class);

    $em->getConnection()->executeStatement(
      "UPDATE users SET status = 'active' WHERE email = ?",
      [$email],
    );

    // Verify user exists and password is correct
    $row = $em->getConnection()->fetchAssociative(
      'SELECT id, email, password, status FROM users WHERE email = ?',
      [$email],
    );

    if (!$row) {
      throw new RuntimeException("User not found after creation: {$email}");
    }

    $passwordHash = is_string($row['password']) ? $row['password'] : '';
    $status = is_string($row['status']) ? $row['status'] : '';

    if (!password_verify($password, $passwordHash)) {
      throw new RuntimeException("Password verification failed. Hash: {$passwordHash}");
    }

    if ('active' !== $status) {
      throw new RuntimeException("User status is not active: {$status}");
    }

    // Clear entity manager to ensure fresh data
    $em->clear();
  }

  /**
   * Decode JSON response content to array.
   *
   * @param string $content Response content
   *
   * @return array<string, mixed>
   */
  protected function decodeJsonResponse(string $content): array
  {
    $data = json_decode($content, true);
    if (!is_array($data)) {
      return [];
    }
    // Filter to ensure string keys for PHPStan
    $result = [];
    foreach ($data as $key => $value) {
      if (is_string($key)) {
        $result[$key] = $value;
      }
    }

    return $result;
  }
}
