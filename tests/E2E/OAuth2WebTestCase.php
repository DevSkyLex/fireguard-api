<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

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

  /**
   * Constant SEEDED_ADMIN_EMAIL.
   *
   * The fixtures account used to authenticate flows that need a real user.
   */
  protected const string SEEDED_ADMIN_EMAIL = 'admin@fireguard.local';

  /**
   * Constant SEEDED_ADMIN_PASSWORD.
   */
  protected const string SEEDED_ADMIN_PASSWORD = 'Admin123!';

  protected ?string $accessToken = null;

  /**
   * Create a client against the seeded databases.
   *
   * The fixture baseline is seeded once, into `fireguard_*_test` by
   * `make test-db` (phpunit.dist.xml, which runs this suite alongside the
   * others) or into `fireguard_*_e2e` by CI (phpunit.e2e.xml). Loading it per
   * test instead cost ~5s each and bought nothing: DAMA wraps every test in a
   * transaction it rolls back, so the seeded data is already pristine at the
   * start of each one, and the test's own writes still disappear at the end.
   *
   * That rollback only isolates tests from each other *inside one process*.
   * Isolation from everything outside it — a concurrent run, a reseed, a psql
   * session — comes from tests/bootstrap.php, which clones both databases per
   * run so nothing else can reach the rows these exact-count assertions read.
   */
  protected static function createClientWithFixtures(): KernelBrowser
  {
    $client = static::createClient();
    $client->enableReboot();

    return $client;
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
   * Sign a seeded user in and return their access token.
   *
   * Token revocation and introspection are restricted to authenticated
   * callers, and a `client_credentials` token cannot stand in: the
   * authenticator resolves a `sub` claim to a real user, which a machine token
   * has none of. Flows exercising those two endpoints therefore log in first.
   *
   * @param KernelBrowser $client the E2E client
   * @param string $email the seeded account's address
   * @param string $password the seeded account's password
   *
   * @return string the bearer token to send on subsequent calls
   */
  protected function authenticateAsSeededAdmin(
    KernelBrowser $client,
    string $email = self::SEEDED_ADMIN_EMAIL,
    string $password = self::SEEDED_ADMIN_PASSWORD,
  ): string {
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => $email,
        'password' => $password,
      ]) ?: '',
    );

    $response = $client->getResponse();
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Login should succeed for the seeded account. Response: ' . $response->getContent(),
    );

    $token = $this->decodeJsonResponse($response->getContent() ?: '{}')['access_token'] ?? null;
    self::assertTrue(is_string($token) && '' !== $token, 'Login should return an access_token.');

    return $token;
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
