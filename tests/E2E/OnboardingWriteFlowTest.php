<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function is_string;
use function json_encode;
use function uniqid;

/**
 * End-to-end write-flow tests for the organization onboarding step execution.
 *
 * Drives the POST /api/onboarding/organization/steps/{stepKey}/execute route to
 * cover ExecuteOrganizationOnboardingStepProcessor: the success branch (a fresh
 * user confirming create_organization), the conflict branch (executing a step on
 * an already-completed session), and the unauthenticated guard.
 */
final class OnboardingWriteFlowTest extends OAuth2WebTestCase
{
  private const string ADMIN_EMAIL = 'admin@fireguard.local';

  private const string ADMIN_PASSWORD = 'Admin123!';

  private const string EXECUTE_CREATE_ORGANIZATION_URI = '/api/onboarding/organization/steps/create_organization/execute';

  /**
   * Success branch: a fresh user starts the flow, creates the organization via
   * the dedicated endpoint, then confirms the create_organization step. The
   * processor must return 200 and the advanced flow state.
   */
  public function testExecuteCreateOrganizationStepAdvancesFlow(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'onboarding-write-' . uniqid() . '@example.com';
    $password = 'Password123!';
    $orgName = 'Fireguard Write ' . uniqid();

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    $authServer = [
      'CONTENT_TYPE' => 'application/ld+json',
      'HTTP_ACCEPT' => 'application/ld+json',
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];

    // Initialize the persisted session before the org exists so the org
    // createdAt is later than the session createdAt (adoption guard).
    $client->request(
      method: 'POST',
      uri: '/api/onboarding/organization/start',
      server: $authServer,
      content: json_encode(['reset' => false]) ?: '',
    );
    $this->assertContains(
      $client->getResponse()->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Start onboarding should succeed. Response: ' . $client->getResponse()->getContent(),
    );

    // Create the organization via its dedicated endpoint first.
    $client->request(
      method: 'POST',
      uri: '/api/organizations',
      server: $authServer,
      content: json_encode(['name' => $orgName]) ?: '',
    );
    $this->assertContains(
      $client->getResponse()->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'POST /api/organizations should succeed before confirming the step. Response: ' . $client->getResponse()->getContent(),
    );

    // Confirm the create_organization step (empty payload — org already created).
    $client->request(
      method: 'POST',
      uri: self::EXECUTE_CREATE_ORGANIZATION_URI,
      server: $authServer,
      content: '{}',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Execute create_organization step should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertSame('organization', $data['flow']);
    $this->assertSame('in_progress', $data['state']);
    $this->assertSame('select_plan', $data['nextStep']);
    $this->assertNotNull($data['targetOrganizationId']);
    $this->assertTrue($data['canRollback']);
  }

  /**
   * Conflict branch: the seeded admin already completed onboarding, so executing
   * any step is not available and the processor must translate the LogicException
   * into a 409. Also confirms the seeded target organization is exposed.
   */
  public function testExecuteStepOnCompletedOnboardingReturnsConflict(): void
  {
    $client = static::createClientWithFixtures();

    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $authServer = [
      'CONTENT_TYPE' => 'application/ld+json',
      'HTTP_ACCEPT' => 'application/ld+json',
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];

    // The seeded admin session is completed and pinned to the seeded organization.
    $client->request(
      method: 'GET',
      uri: '/api/onboarding/organization',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );
    $this->assertSame(
      Response::HTTP_OK,
      $client->getResponse()->getStatusCode(),
      'GET onboarding flow should succeed for seeded admin. Response: ' . $client->getResponse()->getContent(),
    );

    $flow = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $this->assertSame('completed', $flow['state']);
    $this->assertSame(OrganizationFixtures::ORGANIZATION_ID, $flow['targetOrganizationId']);

    // Executing a step on a completed flow is not available -> 409 conflict.
    $client->request(
      method: 'POST',
      uri: self::EXECUTE_CREATE_ORGANIZATION_URI,
      server: $authServer,
      content: '{}',
    );

    $this->assertSame(
      Response::HTTP_CONFLICT,
      $client->getResponse()->getStatusCode(),
      'Executing a step on a completed onboarding flow should return 409. Response: ' . $client->getResponse()->getContent(),
    );
  }

  /**
   * Route existence + guard: an unauthenticated call must be rejected with
   * 401/403, never 404.
   */
  public function testExecuteStepRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: self::EXECUTE_CREATE_ORGANIZATION_URI,
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: '{}',
    );

    $this->assertContains(
      $client->getResponse()->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Execute step endpoint should require authentication. Response: ' . $client->getResponse()->getContent(),
    );
  }

  private function loginAndGetUserAccessToken(KernelBrowser $client, string $email, string $password): string
  {
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
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'User login should succeed for onboarding write flow. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    $this->assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }
}
