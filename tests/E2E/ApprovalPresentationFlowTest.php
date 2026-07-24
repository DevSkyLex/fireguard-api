<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function basename;
use function is_array;
use function is_string;
use function json_encode;
use function str_contains;
use function uniqid;

/**
 * Test ApprovalPresentationFlow.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalPresentationFlowTest extends OAuth2WebTestCase
{
  private const string FAKE_REQUEST_ID = '00000000-0000-4000-8000-000000000000';

  private const string FAKE_ORGANIZATION_ID = '11111111-1111-4111-8111-111111111111';

  /**
   * Happy path: the reference catalog of gateable action types is readable by
   * any authenticated user (GET /api/approvals/action-types, ROLE_USER).
   */
  public function testAuthenticatedUserListsApprovalActionTypeCatalog(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'approval-catalog-' . uniqid() . '@example.com';
    $password = 'CatalogPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    $client->request(
      method: 'GET',
      uri: '/api/approvals/action-types',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Approval action type catalog should be readable. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $members = $this->getCollectionMembers($data);
    $this->assertNotEmpty($members, 'Approval action type catalog should not be empty.');

    $first = $members[0];
    $this->assertArrayHasKey('value', $first);
    $this->assertArrayHasKey('label', $first);
    $this->assertTrue(is_string($first['value'] ?? null), 'Action type value should be a string.');
    $this->assertTrue(is_string($first['label'] ?? null), 'Action type label should be a string.');
  }

  /**
   * Guard: the action type catalog requires authentication.
   */
  public function testApprovalActionTypeCatalogRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/approvals/action-types',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();
    $this->assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'Action type catalog route should exist.',
    );
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Action type catalog should be guarded. Response: ' . $response->getContent(),
    );
  }

  /**
   * Happy path: an organization admin (organization.* wildcard, hence
   * organization.approvals.read) can list the approval request queue.
   * The freshly created organization has no pending requests, so the list is
   * empty but structurally a Hydra collection.
   */
  public function testOrganizationAdminListsApprovalRequests(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'approval-owner-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    $organizationId = $this->createOrganization($client, $token, 'Approval Queue Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/approval-requests',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Organization admin should be able to list approval requests. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertArrayHasKey('member', $data, 'Approval request list should be a Hydra collection.');
    $this->assertTrue(is_array($data['member'] ?? null), 'Hydra member should be an array.');
    $this->assertArrayHasKey('totalItems', $data, 'Approval request list should expose totalItems.');
  }

  /**
   * Guard: listing approval requests requires authentication.
   */
  public function testListApprovalRequestsRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . self::FAKE_ORGANIZATION_ID . '/approval-requests',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();
    $this->assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'List approval requests route should exist.',
    );
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Listing approval requests should be guarded. Response: ' . $response->getContent(),
    );
  }

  /**
   * Guard: getting a single approval request requires authentication.
   *
   * Happy path is skipped: seeding a pending approval request requires
   * triggering a deferred gated action, which the fixtures do not provide.
   */
  public function testGetApprovalRequestRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . self::FAKE_ORGANIZATION_ID . '/approval-requests/' . self::FAKE_REQUEST_ID,
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();
    $this->assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'Get approval request route should exist.',
    );
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Getting an approval request should be guarded. Response: ' . $response->getContent(),
    );
  }

  /**
   * Guard: approving an approval request requires authentication.
   *
   * Happy path is skipped: a decidable pending request cannot be seeded.
   */
  public function testApproveApprovalRequestRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::FAKE_ORGANIZATION_ID . '/approval-requests/' . self::FAKE_REQUEST_ID . '/approve',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode(['decisionNote' => 'ok']) ?: '',
    );

    $response = $client->getResponse();
    $this->assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'Approve approval request route should exist.',
    );
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Approving an approval request should be guarded. Response: ' . $response->getContent(),
    );
  }

  /**
   * Guard: rejecting an approval request requires authentication.
   *
   * Happy path is skipped: a decidable pending request cannot be seeded.
   */
  public function testRejectApprovalRequestRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::FAKE_ORGANIZATION_ID . '/approval-requests/' . self::FAKE_REQUEST_ID . '/reject',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode(['decisionNote' => 'no']) ?: '',
    );

    $response = $client->getResponse();
    $this->assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'Reject approval request route should exist.',
    );
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Rejecting an approval request should be guarded. Response: ' . $response->getContent(),
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
      'User login should succeed for E2E flow. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    $this->assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }

  private function createOrganization(KernelBrowser $client, string $token, string $name): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['name' => $name]) ?: '',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');

    $id = $data['id'] ?? null;
    if (is_string($id) && '' !== $id) {
      return $id;
    }

    $iri = $data['@id'] ?? null;
    if (is_string($iri) && str_contains($iri, '/')) {
      $candidate = basename($iri);

      return '' !== $candidate ? $candidate : null;
    }

    return null;
  }

  /**
   * @param array<string, mixed> $data
   *
   * @return list<array<string, mixed>>
   */
  private function getCollectionMembers(array $data): array
  {
    $members = $data['member'] ?? [];

    if (!is_array($members)) {
      return [];
    }

    $result = [];
    foreach ($members as $member) {
      if (!is_array($member)) {
        continue;
      }

      $normalized = [];
      foreach ($member as $key => $value) {
        if (is_string($key)) {
          $normalized[$key] = $value;
        }
      }

      $result[] = $normalized;
    }

    return $result;
  }
}
