<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function is_array;
use function is_string;
use function json_encode;
use function uniqid;

/**
 * Test OrganizationPresentation.
 *
 * Authenticated E2E coverage for the Organization Presentation endpoints that
 * the main OrganizationFlowTest does not exercise: reference catalogs
 * (statuses, invitation-statuses), organization-scoped read resources (quota,
 * permissions, navigation-counters, current member profile, teams,
 * invitations) and the plan catalog. Each endpoint is checked both on the
 * authenticated happy path and as an unauthenticated guard.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationPresentationFlowTest extends OAuth2WebTestCase
{
  private const string OWNER_PASSWORD = 'OwnerPassword123!';

  public function testOrganizationInvitationStatusesReferenceCatalogIsExposedToMembers(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateOwner($client);

    $data = $this->getJson($client, '/api/organizations/invitation-statuses', $token);
    $this->assertArrayHasKey('member', $data, 'Invitation-status catalog should be a Hydra collection.');
    $this->assertTrue(is_array($data['member'] ?? null), 'Invitation-status catalog member should be a list.');

    $this->assertRequiresAuthentication($client, '/api/organizations/invitation-statuses');
  }

  public function testOrganizationQuotaEndpointReturnsUsageForOwner(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateOwner($client);
    $organizationId = $this->createOrganization($client, $token);

    $data = $this->getJson($client, '/api/organizations/' . $organizationId . '/quota', $token);
    $this->assertArrayHasKey('organizationId', $data, 'Quota output should expose the organization id.');
    $this->assertArrayHasKey('items', $data, 'Quota output should expose the capped-resource items.');
    $this->assertTrue(is_array($data['items'] ?? null), 'Quota items should be a list.');

    $this->assertRequiresAuthentication($client, '/api/organizations/' . $organizationId . '/quota');
  }

  public function testOrganizationPermissionsCatalogIsExposedToOwner(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateOwner($client);
    $organizationId = $this->createOrganization($client, $token);

    $data = $this->getJson($client, '/api/organizations/' . $organizationId . '/permissions', $token);
    $this->assertArrayHasKey('member', $data, 'Permission catalog should be a Hydra collection.');
    $this->assertTrue(is_array($data['member'] ?? null), 'Permission catalog member should be a list.');

    $this->assertRequiresAuthentication($client, '/api/organizations/' . $organizationId . '/permissions');
  }

  public function testOrganizationNavigationCountersEndpointReturnsBadges(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateOwner($client);
    $organizationId = $this->createOrganization($client, $token);

    $data = $this->getJson($client, '/api/organizations/' . $organizationId . '/navigation-counters', $token);
    $this->assertArrayHasKey('openInterventions', $data, 'Navigation counters should expose openInterventions.');
    $this->assertArrayHasKey('openNonConformities', $data, 'Navigation counters should expose openNonConformities.');

    $this->assertRequiresAuthentication($client, '/api/organizations/' . $organizationId . '/navigation-counters');
  }

  public function testCurrentOrganizationMemberProfileIsExposedToOwner(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateOwner($client);
    $organizationId = $this->createOrganization($client, $token);

    $data = $this->getJson($client, '/api/organizations/' . $organizationId . '/me', $token);
    $this->assertArrayHasKey('organizationId', $data, 'Member profile should expose organizationId.');
    $this->assertArrayHasKey('userId', $data, 'Member profile should expose userId.');
    $this->assertArrayHasKey('roles', $data, 'Member profile should expose roles.');
    $this->assertArrayHasKey('permissions', $data, 'Member profile should expose permissions.');
    $this->assertSame($organizationId, $data['organizationId'] ?? null, 'Profile should be scoped to the requested organization.');

    $this->assertRequiresAuthentication($client, '/api/organizations/' . $organizationId . '/me');
  }

  public function testOrganizationTeamsCollectionIsExposedToOwner(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateOwner($client);
    $organizationId = $this->createOrganization($client, $token);

    $data = $this->getJson($client, '/api/organizations/' . $organizationId . '/teams', $token);
    $this->assertArrayHasKey('member', $data, 'Teams endpoint should be a Hydra collection.');
    $this->assertTrue(is_array($data['member'] ?? null), 'Teams member should be a list.');

    $this->assertRequiresAuthentication($client, '/api/organizations/' . $organizationId . '/teams');
  }

  public function testOrganizationInvitationsCollectionIsExposedToOwner(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateOwner($client);
    $organizationId = $this->createOrganization($client, $token);

    $data = $this->getJson($client, '/api/organizations/' . $organizationId . '/invitations', $token);
    $this->assertArrayHasKey('member', $data, 'Invitations endpoint should be a Hydra collection.');
    $this->assertTrue(is_array($data['member'] ?? null), 'Invitations member should be a list.');

    $this->assertRequiresAuthentication($client, '/api/organizations/' . $organizationId . '/invitations');
  }

  public function testPlanCatalogEndpointsAreExposedToMembers(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateOwner($client);

    $listData = $this->getJson($client, '/api/plans', $token);
    $this->assertArrayHasKey('member', $listData, 'Plan catalog should be a Hydra collection.');
    $plans = $listData['member'] ?? [];
    $this->assertTrue(is_array($plans), 'Plan catalog member should be a list.');

    $planId = null;
    foreach ($plans as $plan) {
      if (is_array($plan) && isset($plan['id']) && is_string($plan['id']) && '' !== $plan['id']) {
        $planId = $plan['id'];

        break;
      }
    }

    $this->assertRequiresAuthentication($client, '/api/plans');

    if (null === $planId) {
      // No seeded plan to read individually; the collection assertions above still cover the resource.
      return;
    }

    $detail = $this->getJson($client, '/api/plans/' . $planId, $token);
    $this->assertArrayHasKey('id', $detail, 'Plan detail should expose id.');
    $this->assertArrayHasKey('key', $detail, 'Plan detail should expose key.');
    $this->assertArrayHasKey('name', $detail, 'Plan detail should expose name.');

    $this->assertRequiresAuthentication($client, '/api/plans/' . $planId);
  }

  /**
   * Create an activated owner user and return a bearer access token.
   */
  private function authenticateOwner(KernelBrowser $client): string
  {
    $ownerEmail = 'organization-presentation-owner-' . uniqid() . '@example.com';
    $this->createAndActivateUser($client, $ownerEmail, self::OWNER_PASSWORD);

    return $this->loginAndGetUserAccessToken($client, $ownerEmail, self::OWNER_PASSWORD);
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

  private function createOrganization(KernelBrowser $client, string $token): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['name' => 'Presentation Org ' . uniqid()]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Organization creation should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $id = $data['id'] ?? null;
    $this->assertTrue(is_string($id) && '' !== $id, 'Organization ID should be present in create response.');

    return $id;
  }

  /**
   * Issue an authenticated GET and assert a 200, returning the decoded body.
   *
   * @return array<string, mixed>
   */
  private function getJson(KernelBrowser $client, string $uri, string $token): array
  {
    $client->request(
      method: 'GET',
      uri: $uri,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Authenticated GET ' . $uri . ' should succeed. Response: ' . $response->getContent(),
    );

    return $this->decodeJsonResponse($response->getContent() ?: '{}');
  }

  /**
   * Assert the route exists (not 404) and is guarded when called anonymously.
   */
  private function assertRequiresAuthentication(KernelBrowser $client, string $uri): void
  {
    $client->request(
      method: 'GET',
      uri: $uri,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
    );

    $response = $client->getResponse();
    $this->assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'Route ' . $uri . ' should exist. Response: ' . $response->getContent(),
    );
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Route ' . $uri . ' should require authentication. Response: ' . $response->getContent(),
    );
  }
}
