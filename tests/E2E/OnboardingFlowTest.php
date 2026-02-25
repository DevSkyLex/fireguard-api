<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function is_string;
use function json_encode;
use function uniqid;

/**
 * End-to-end tests for organization onboarding flow.
 */
final class OnboardingFlowTest extends OAuth2WebTestCase
{
  public function testOnboardingEndpointsRequireAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $endpoints = [
      ['GET', '/api/onboarding/organization'],
      ['POST', '/api/onboarding/organization/start'],
      ['POST', '/api/onboarding/organization/steps/create_organization/execute'],
      ['POST', '/api/onboarding/organization/rollback'],
    ];

    foreach ($endpoints as [$method, $uri]) {
      $client->request(
        method: $method,
        uri: $uri,
        server: [
          'CONTENT_TYPE' => 'application/ld+json',
          'HTTP_ACCEPT' => 'application/ld+json',
        ],
        content: '{}',
      );

      $response = $client->getResponse();
      $this->assertContains(
        $response->getStatusCode(),
        [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
        "Endpoint {$method} {$uri} should require authentication. Response: " . $response->getContent(),
      );
    }
  }

  public function testGetFlowReturnsInitialState(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'onboarding-get-' . uniqid() . '@example.com';
    $password = 'Password123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    $client->request(
      method: 'GET',
      uri: '/api/onboarding/organization',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'GET /api/onboarding/organization should return 200 for authenticated user. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');

    $this->assertSame('organization', $data['flow']);
    $this->assertSame('in_progress', $data['state']);
    $this->assertSame('create_organization', $data['nextStep']);
    $this->assertIsArray($data['steps']);
    $this->assertCount(2, $data['steps']);
  }

  public function testCompleteOrganizationOnboardingFlow(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'onboarding-flow-' . uniqid() . '@example.com';
    $password = 'Password123!';
    $orgName = 'Fireguard E2E ' . uniqid();

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    // Step 1: Start the onboarding session.
    $client->request(
      method: 'POST',
      uri: '/api/onboarding/organization/start',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['reset' => false]) ?: '',
    );

    $startResponse = $client->getResponse();
    $this->assertContains(
      $startResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Start onboarding should succeed. Response: ' . $startResponse->getContent(),
    );

    $startData = $this->decodeJsonResponse($startResponse->getContent() ?: '{}');
    $this->assertSame('in_progress', $startData['state']);
    $this->assertSame('create_organization', $startData['nextStep']);

    // Step 2a: Create the organization via the dedicated endpoint first.
    $client->request(
      method: 'POST',
      uri: '/api/organizations',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['name' => $orgName]) ?: '',
    );

    $this->assertContains(
      $client->getResponse()->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'POST /api/organizations should succeed before confirming onboarding step. Response: ' . $client->getResponse()->getContent(),
    );

    // Step 2b: Confirm the create_organization step (empty payload — org already created above).
    $client->request(
      method: 'POST',
      uri: '/api/onboarding/organization/steps/create_organization/execute',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $createOrgResponse = $client->getResponse();
    $this->assertContains(
      $createOrgResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Execute create_organization step should succeed. Response: ' . $createOrgResponse->getContent(),
    );

    $createOrgData = $this->decodeJsonResponse($createOrgResponse->getContent() ?: '{}');
    $this->assertSame('in_progress', $createOrgData['state']);
    $this->assertSame('invite_members', $createOrgData['nextStep']);
    $this->assertNotNull($createOrgData['targetOrganizationId']);
    $this->assertTrue($createOrgData['canRollback']);

    $organizationId = $createOrgData['targetOrganizationId'];
    $this->assertIsString($organizationId);

    // Step 3: Skip the invite_members step (it is optional).
    $client->request(
      method: 'POST',
      uri: '/api/onboarding/organization/steps/invite_members/skip',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $skipResponse = $client->getResponse();
    $this->assertContains(
      $skipResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Skip invite_members step should succeed. Response: ' . $skipResponse->getContent(),
    );

    $skipData = $this->decodeJsonResponse($skipResponse->getContent() ?: '{}');
    $this->assertSame('completed', $skipData['state']);
    $this->assertNull($skipData['nextStep']);
  }

  public function testExecuteWrongStepReturnsConflict(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'onboarding-wrong-' . uniqid() . '@example.com';
    $password = 'Password123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    // Attempt to execute invite_members before create_organization is confirmed.
    $client->request(
      method: 'POST',
      uri: '/api/onboarding/organization/steps/invite_members/execute',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_CONFLICT,
      $response->getStatusCode(),
      'Executing wrong step should return 409. Response: ' . $response->getContent(),
    );
  }

  public function testRollbackAfterCreateOrganization(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'onboarding-rollback-' . uniqid() . '@example.com';
    $password = 'Password123!';
    $orgName = 'Fireguard Rollback ' . uniqid();

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    // Create the organization via the dedicated endpoint first.
    $client->request(
      method: 'POST',
      uri: '/api/organizations',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['name' => $orgName]) ?: '',
    );

    $this->assertContains(
      $client->getResponse()->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'POST /api/organizations should succeed before confirming onboarding step. Response: ' . $client->getResponse()->getContent(),
    );

    // Confirm the create_organization onboarding step (empty payload).
    $client->request(
      method: 'POST',
      uri: '/api/onboarding/organization/steps/create_organization/execute',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $createOrgResponse = $client->getResponse();
    $this->assertContains(
      $createOrgResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Create organization step should succeed before rollback test. Response: ' . $createOrgResponse->getContent(),
    );

    $createOrgData = $this->decodeJsonResponse($createOrgResponse->getContent() ?: '{}');
    $this->assertTrue($createOrgData['canRollback'], 'Rollback should be available after creating organization.');

    // Rollback the create_organization step.
    $client->request(
      method: 'POST',
      uri: '/api/onboarding/organization/rollback',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '',
    );

    $rollbackResponse = $client->getResponse();
    $this->assertContains(
      $rollbackResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Rollback should succeed after create_organization step. Response: ' . $rollbackResponse->getContent(),
    );

    $rollbackData = $this->decodeJsonResponse($rollbackResponse->getContent() ?: '{}');
    $this->assertSame('in_progress', $rollbackData['state']);
    $this->assertSame('create_organization', $rollbackData['nextStep']);
    $this->assertNull($rollbackData['targetOrganizationId']);
    $this->assertFalse($rollbackData['canRollback']);
  }

  public function testStartWithResetReturnsConsistentState(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'onboarding-reset-' . uniqid() . '@example.com';
    $password = 'Password123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    // First start without reset, creates a fresh session.
    $client->request(
      method: 'POST',
      uri: '/api/onboarding/organization/start',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['reset' => false]) ?: '',
    );

    $this->assertContains(
      $client->getResponse()->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
    );

    // Second start with reset=true deletes the session and recreates it.
    // No org exists, so the flow is back to create_organization.
    $client->request(
      method: 'POST',
      uri: '/api/onboarding/organization/start',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['reset' => true]) ?: '',
    );

    $resetResponse = $client->getResponse();
    $this->assertContains(
      $resetResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Start with reset=true should succeed. Response: ' . $resetResponse->getContent(),
    );

    $resetData = $this->decodeJsonResponse($resetResponse->getContent() ?: '{}');
    $this->assertSame('in_progress', $resetData['state']);
    $this->assertSame('create_organization', $resetData['nextStep']);
    $this->assertFalse($resetData['canRollback']);
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
      'User login should succeed for E2E flow. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    $this->assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }
}
