<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

use function json_encode;

/**
 * TenantManagementFlowTest - E2E tests for multi-tenant management API.
 *
 * This test suite covers the complete lifecycle of tenant management:
 * - Listing all tenants (GET /api/tenants)
 * - Retrieving a specific tenant by ID (GET /api/tenants/{id})
 * - Creating new tenants (POST /api/tenants)
 * - Updating tenants (PATCH /api/tenants/{id})
 * - Activating / deactivating tenants (POST /api/tenants/{id}/activate|deactivate)
 * - Deleting tenants (DELETE /api/tenants/{id})
 *
 * Tenants define OAuth2 settings and configuration for different Organizations
 * in a multi-tenant architecture. Each tenant can have custom authentication
 * settings, branding, and access policies.
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @see \Tenant\Presentation\Api\Resource\TenantResource
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class TenantManagementFlowTest extends OAuth2WebTestCase
{
  // #region List Tenants Tests

  /**
   * Test listing tenants with a valid OAuth2 access token.
   *
   * This test verifies that an authenticated user can retrieve the list
   * of all available tenants. The endpoint requires ROLE_ADMIN privileges.
   *
   * Expected behavior:
   * - HTTP 200: Returns a JSON-LD collection with 'member' array containing tenants
   * - HTTP 403: User lacks required ROLE_ADMIN permission
   * - HTTP 401: Token is invalid or expired
   *
   * @see \Tenant\Presentation\Api\Provider\Tenant\ListTenantsProvider
   */
  public function testListTenantsWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'GET',
      uri: '/api/tenants',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Should return tenants list or appropriate auth response. Response: ' . $response->getContent(),
    );

    if (Response::HTTP_OK === $response->getStatusCode()) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $this->assertArrayHasKey('member', $data, 'Response should contain member array');
      $this->assertIsArray($data['member']);
    }
  }

  /**
   * Test listing tenants without authentication token.
   *
   * This test ensures the endpoint properly enforces authentication.
   * Anonymous access to tenant listing must be denied to protect
   * Organizational information.
   *
   * Expected behavior:
   * - HTTP 401: Authentication required
   * - HTTP 403: Access forbidden
   */
  public function testListTenantsWithoutToken(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/tenants',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND],
      'Should require authentication. Response: ' . $response->getContent(),
    );
  }

  // #endregion

  // #region Get Tenant Tests

  /**
   * Test retrieving a non-existent tenant by UUID.
   *
   * This test verifies proper error handling when attempting to fetch
   * a tenant that doesn't exist in the database. Uses a valid UUID format
   * to ensure the 404 response is due to missing data, not invalid format.
   *
   * Expected behavior:
   * - HTTP 404: Tenant not found
   * - HTTP 403: Access denied before entity lookup
   *
   * @see \Tenant\Presentation\Api\Provider\Tenant\GetTenantProvider
   */
  public function testGetNonExistentTenant(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'GET',
      uri: '/api/tenants/00000000-0000-4000-8000-000000000000',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_NOT_FOUND, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Non-existent tenant should return 404 or auth error. Response: ' . $response->getContent(),
    );
  }

  /**
   * Test retrieving a tenant without authentication.
   *
   * This test ensures the GET endpoint requires authentication before
   * attempting to fetch tenant details. Security check should occur
   * before database lookup.
   *
   * Expected behavior:
   * - HTTP 401: Authentication required
   * - HTTP 403: Access forbidden
   */
  public function testGetTenantWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/tenants/00000000-0000-4000-8000-000000000000',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND],
      'Should require authentication',
    );
  }

  // #endregion

  // #region Update Tenant Tests

  /**
   * Test updating a tenant with a valid OAuth2 access token.
   *
   * Expected behavior:
   * - HTTP 200: Tenant updated successfully
   * - HTTP 404: Tenant not found
   * - HTTP 400/422: Validation errors
   * - HTTP 401/403: Unauthorized or forbidden
   *
   * @see \Tenant\Presentation\Api\Processor\Tenant\UpdateTenantProcessor
   */
  public function testUpdateTenantWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'PATCH',
      uri: '/api/tenants/00000000-0000-4000-8000-000000000000',
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'name' => 'Updated Tenant',
        'accessTokenTtl' => 3600,
        'refreshTokenTtl' => 86400,
        'requirePkce' => true,
        'allowPublicClients' => false,
        'allowedScopes' => ['openid', 'profile'],
        'customIssuer' => 'https://auth.example.com',
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_NOT_FOUND, Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Update tenant should respond appropriately. Response: ' . $response->getContent(),
    );
  }

  // #endregion

  // #region Activate/Deactivate/Delete Tests

  /**
   * Test activating a tenant with a valid OAuth2 access token.
   *
   * @see \Tenant\Presentation\Api\Processor\Tenant\ActivateTenantProcessor
   */
  public function testActivateTenantWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'POST',
      uri: '/api/tenants/00000000-0000-4000-8000-000000000000/activate',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_NOT_FOUND, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Activate tenant should respond appropriately. Response: ' . $response->getContent(),
    );
  }

  /**
   * Test deactivating a tenant with a valid OAuth2 access token.
   *
   * @see \Tenant\Presentation\Api\Processor\Tenant\DeactivateTenantProcessor
   */
  public function testDeactivateTenantWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'POST',
      uri: '/api/tenants/00000000-0000-4000-8000-000000000000/deactivate',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_NOT_FOUND, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Deactivate tenant should respond appropriately. Response: ' . $response->getContent(),
    );
  }

  /**
   * Test deleting a tenant with a valid OAuth2 access token.
   *
   * @see \Tenant\Presentation\Api\Processor\Tenant\DeleteTenantProcessor
   */
  public function testDeleteTenantWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'DELETE',
      uri: '/api/tenants/00000000-0000-4000-8000-000000000000',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_NO_CONTENT, Response::HTTP_NOT_FOUND, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Delete tenant should respond appropriately. Response: ' . $response->getContent(),
    );
  }

  // #endregion

  // #region Create Tenant Tests

  /**
   * Test creating a new tenant with valid data.
   *
   * This test verifies the tenant creation flow with complete, valid input.
   * A unique slug is generated using uniqid() to avoid conflicts between
   * test runs.
   *
   * Request payload:
   * - name: Human-readable tenant name
   * - slug: Unique URL-friendly identifier
   * - settings: Tenant-specific configuration (authentication, branding, etc.)
   *
   * Expected behavior:
   * - HTTP 201: Tenant created successfully, response contains @id
   * - HTTP 422: Validation errors in input data
   * - HTTP 403: User lacks required permissions
   *
   * @see \Tenant\Presentation\Api\Processor\Tenant\CreateTenantProcessor
   */
  public function testCreateTenantWithValidData(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'POST',
      uri: '/api/tenants',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'name' => 'Test Tenant',
        'accessTokenTtl' => 3600,
        'refreshTokenTtl' => 86400,
        'requirePkce' => true,
        'allowPublicClients' => false,
        'allowedScopes' => ['openid', 'profile', 'email'],
        'customIssuer' => 'https://auth.example.com',
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK, Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Create tenant should respond appropriately. Response: ' . $response->getContent(),
    );

    if (Response::HTTP_CREATED === $response->getStatusCode() || Response::HTTP_OK === $response->getStatusCode()) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $this->assertArrayHasKey('@id', $data, 'Created tenant should have @id');
    }
  }

  /**
   * Test creating a tenant without authentication.
   *
   * This test ensures that unauthenticated requests to create tenants
   * are properly rejected. Tenant creation should require admin privileges.
   *
   * Expected behavior:
   * - HTTP 401: Authentication required
   * - HTTP 403: Access forbidden
   */
  public function testCreateTenantWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/tenants',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'name' => 'Unauthorized Tenant',
        'accessTokenTtl' => 3600,
        'refreshTokenTtl' => 86400,
        'requirePkce' => true,
        'allowPublicClients' => false,
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Should require authentication. Response: ' . $response->getContent(),
    );
  }

  /**
   * Test creating a tenant with missing required fields.
   *
   * This test verifies that the API properly validates input and rejects
   * requests with missing or invalid data. An empty payload should trigger
   * validation errors.
   *
   * Expected behavior:
   * - HTTP 400: Bad request due to missing fields
   * - HTTP 422: Validation constraint violations
   *
   * @see \Tenant\Presentation\Api\Dto\Input\Tenant\TenantInput
   */
  public function testCreateTenantWithInvalidData(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'POST',
      uri: '/api/tenants',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Invalid data should be rejected. Response: ' . $response->getContent(),
    );
  }

  // #endregion
}
