<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\DataFixtures\EquipmentFixtures;
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Inspection\Infrastructure\DataFixtures\InspectionFixtures;
use OAuth\Infrastructure\DataFixtures\ClientFixtures;
use Onboarding\Infrastructure\DataFixtures\OnboardingFixtures;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
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
    static::loadTestFixtures($client);
    $client->enableReboot();

    return $client;
  }

  /**
   * Load fixtures for testing using proper executor.
   */
  protected static function loadTestFixtures(KernelBrowser $client): void
  {
    $container = $client->getContainer();

    /** @var EntityManagerInterface $authEntityManager */
    $authEntityManager = $container->get('doctrine.orm.auth_entity_manager');

    $authLoader = new Loader();

    /** @var ClientFixtures $clientFixtures */
    $clientFixtures = $container->get(ClientFixtures::class);
    /** @var UserFixtures $userFixtures */
    $userFixtures = $container->get(UserFixtures::class);

    $authLoader->addFixture($clientFixtures);
    $authLoader->addFixture($userFixtures);

    $authExecutor = new ORMExecutor($authEntityManager);
    // Append mode avoids purge; each test is isolated by transaction rollback.
    $authExecutor->execute($authLoader->getFixtures(), true);
    $authEntityManager->clear();

    /** @var EntityManagerInterface $mainEntityManager */
    $mainEntityManager = $container->get('doctrine.orm.main_entity_manager');

    $mainLoader = new Loader();
    /** @var OrganizationFixtures $organizationFixtures */
    $organizationFixtures = $container->get(OrganizationFixtures::class);
    /** @var FacilityFixtures $facilityFixtures */
    $facilityFixtures = $container->get(FacilityFixtures::class);
    /** @var EquipmentFixtures $equipmentFixtures */
    $equipmentFixtures = $container->get(EquipmentFixtures::class);
    /** @var InspectionFixtures $inspectionFixtures */
    $inspectionFixtures = $container->get(InspectionFixtures::class);
    /** @var OnboardingFixtures $onboardingFixtures */
    $onboardingFixtures = $container->get(OnboardingFixtures::class);

    $mainLoader->addFixture($organizationFixtures);
    $mainLoader->addFixture($onboardingFixtures);
    $mainLoader->addFixture($facilityFixtures);
    $mainLoader->addFixture($equipmentFixtures);
    $mainLoader->addFixture($inspectionFixtures);

    $executor = new ORMExecutor($mainEntityManager);
    // Append mode avoids purge; each test is isolated by transaction rollback.
    $executor->execute($mainLoader->getFixtures(), true);

    $mainEntityManager->clear();
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
