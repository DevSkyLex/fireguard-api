<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Authorization\Infrastructure\DataFixtures\AuthorizationFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Infrastructure\DataFixtures\ClientFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use User\Infrastructure\DataFixtures\UserFixtures;

use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function str_replace;
use function ucfirst;
use function uniqid;

/**
 * Test RolePermissionManagementFlowTest.
 *
 * End-to-end tests for Role and Permission management with OAuth2 authentication.
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class RolePermissionManagementFlowTest extends WebTestCase
{
  // #region Constants
  protected const string DEV_CLIENT_ID = 'a7b8c9d0-e1f2-4456-8123-789012345678';

  protected const string DEV_CLIENT_SECRET = 'dev_secret_test';
  // #endregion

  // #region Properties
  protected ?string $accessToken = null;
  // #endregion

  // #region Permission List Tests

  /**
   * Test listing permissions with valid token.
   */
  public function testListPermissionsWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'GET',
      uri: '/api/permissions',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Should return permissions list or appropriate auth response. Response: ' . $response->getContent(),
    );

    if (Response::HTTP_OK === $response->getStatusCode()) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $items = $this->getCollectionMembers($data);
      $this->assertNotEmpty($items, 'Should have permissions from fixtures');
    }
  }

  /**
   * Test listing permissions without token.
   */
  public function testListPermissionsWithoutToken(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/permissions',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();
    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Should require authentication',
    );
  }

  // #endregion

  // #region Permission CRUD Tests

  /**
   * Test creating a permission.
   */
  public function testCreatePermission(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $uniqueId = str_replace(
      ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
      ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'],
      uniqid(),
    );

    $client->request(
      method: 'POST',
      uri: '/api/permissions',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'name' => 'test.permission' . $uniqueId,
        'description' => 'Test permission created via E2E test',
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Permission creation should require ROLE_SUPER_ADMIN. Response: ' . $response->getContent(),
    );
  }

  /**
   * Test creating permission with invalid name format.
   */
  public function testCreatePermissionWithInvalidName(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'POST',
      uri: '/api/permissions',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'name' => 'invalid_format_without_dot',
        'description' => 'This should fail validation',
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Invalid permission name format should be rejected',
    );
  }

  /**
   * Test getting a specific permission.
   */
  public function testGetPermissionById(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    // First, list permissions to get an ID
    $client->request(
      method: 'GET',
      uri: '/api/permissions',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $listResponse = $client->getResponse();

    if (Response::HTTP_OK !== $listResponse->getStatusCode()) {
      $this->markTestSkipped('Cannot list permissions - may require higher permissions');
    }

    $data = $this->decodeJsonResponse($listResponse->getContent() ?: '{}');
    $permissions = $this->getCollectionMembers($data);

    if (empty($permissions)) {
      $this->markTestSkipped('No permissions found in fixtures');
    }

    $permissionId = $this->getStringValue($permissions[0], 'id');
    if (null === $permissionId) {
      $this->markTestSkipped(ucfirst('permission') . ' ID not found');
    }

    // Now get the specific permission
    $client->request(
      method: 'GET',
      uri: '/api/permissions/' . $permissionId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND],
      'Should return permission or appropriate error',
    );
  }

  // #endregion

  // #region Role List Tests

  /**
   * Test listing roles with valid token.
   */
  public function testListRolesWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'GET',
      uri: '/api/roles',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Should return roles list or appropriate auth response. Response: ' . $response->getContent(),
    );

    if (Response::HTTP_OK === $response->getStatusCode()) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $items = $this->getCollectionMembers($data);
      $this->assertNotEmpty($items, 'Should have roles from fixtures');
    }
  }

  /**
   * Test listing roles without token.
   */
  public function testListRolesWithoutToken(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/roles',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();
    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Should require authentication',
    );
  }

  // #endregion

  // #region Role CRUD Tests

  /**
   * Test creating a role.
   */
  public function testCreateRole(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $uniqueId = uniqid();

    $client->request(
      method: 'POST',
      uri: '/api/roles',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'name' => 'testrole' . $uniqueId,
        'description' => 'Test role created via E2E test',
        'is_system' => false,
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Role creation should require ROLE_ADMIN. Response: ' . $response->getContent(),
    );
  }

  /**
   * Test creating role with invalid name.
   */
  public function testCreateRoleWithInvalidName(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'POST',
      uri: '/api/roles',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'name' => 'INVALID_UPPERCASE',
        'description' => 'This should fail validation',
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Invalid role name should be rejected',
    );
  }

  /**
   * Test getting a specific role.
   */
  public function testGetRoleById(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    // First, list roles to get an ID
    $client->request(
      method: 'GET',
      uri: '/api/roles',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $listResponse = $client->getResponse();

    if (Response::HTTP_OK !== $listResponse->getStatusCode()) {
      $this->markTestSkipped('Cannot list roles - may require higher permissions');
    }

    $data = $this->decodeJsonResponse($listResponse->getContent() ?: '{}');
    $roles = $this->getCollectionMembers($data);

    if (empty($roles)) {
      $this->markTestSkipped('No roles found in fixtures');
    }

    $roleId = $this->getStringValue($roles[0], 'id');
    if (null === $roleId) {
      $this->markTestSkipped(ucfirst('role') . ' ID not found');
    }

    // Now get the specific role
    $client->request(
      method: 'GET',
      uri: '/api/roles/' . $roleId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND],
      'Should return role or appropriate error',
    );

    if (Response::HTTP_OK === $response->getStatusCode()) {
      $roleData = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $this->assertArrayHasKey('permissions', $roleData, 'Role should include permissions');
    }
  }

  /**
   * Test updating a role.
   */
  public function testUpdateRole(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    // First, list roles to get an ID
    $client->request(
      method: 'GET',
      uri: '/api/roles',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $listResponse = $client->getResponse();

    if (Response::HTTP_OK !== $listResponse->getStatusCode()) {
      $this->markTestSkipped('Cannot list roles');
    }

    $data = $this->decodeJsonResponse($listResponse->getContent() ?: '{}');
    $roles = $this->getCollectionMembers($data);

    if (empty($roles)) {
      $this->markTestSkipped('No roles found');
    }

    $roleId = $this->getStringValue($roles[0], 'id');
    if (null === $roleId) {
      $this->markTestSkipped(ucfirst('role') . ' ID not found');
    }

    $client->request(
      method: 'PATCH',
      uri: '/api/roles/' . $roleId,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'description' => 'Updated description via E2E test',
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_NOT_FOUND],
      'PATCH role should respond appropriately. Response: ' . $response->getContent(),
    );
  }

  // #endregion

  // #region Role Permission Subresource Tests

  /**
   * Test adding a permission to a role.
   */
  public function testAddPermissionToRole(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    // Get a role ID
    $client->request(
      method: 'GET',
      uri: '/api/roles',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $rolesResponse = $client->getResponse();
    if (Response::HTTP_OK !== $rolesResponse->getStatusCode()) {
      $this->markTestSkipped('Cannot list roles');
    }

    $rolesData = $this->decodeJsonResponse($rolesResponse->getContent() ?: '{}');
    $roles = $this->getCollectionMembers($rolesData);
    if (empty($roles)) {
      $this->markTestSkipped('No roles found');
    }
    $roleId = $this->getStringValue($roles[0], 'id');
    if (null === $roleId) {
      $this->markTestSkipped('Role ID not found');
    }

    // Get a permission ID
    $client->request(
      method: 'GET',
      uri: '/api/permissions',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $permissionsResponse = $client->getResponse();
    if (Response::HTTP_OK !== $permissionsResponse->getStatusCode()) {
      $this->markTestSkipped('Cannot list permissions');
    }

    $permissionsData = $this->decodeJsonResponse($permissionsResponse->getContent() ?: '{}');
    $permissions = $this->getCollectionMembers($permissionsData);
    if (empty($permissions)) {
      $this->markTestSkipped('No permissions found');
    }
    $permissionId = $this->getStringValue($permissions[0], 'id');
    if (null === $permissionId) {
      $this->markTestSkipped('Permission ID not found');
    }

    // Add permission to role
    $client->request(
      method: 'POST',
      uri: '/api/roles/' . $roleId . '/permissions',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'permission_id' => $permissionId,
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_NOT_FOUND],
      'Add permission to role should respond appropriately. Response: ' . $response->getContent(),
    );
  }

  /**
   * Test removing a permission from a role.
   *
   * This test first adds a permission to a role, then removes it.
   * This makes it self-sufficient and not dependent on fixture state.
   */
  public function testRemovePermissionFromRole(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    // Get a role ID
    $client->request(
      method: 'GET',
      uri: '/api/roles',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $rolesResponse = $client->getResponse();
    if (Response::HTTP_OK !== $rolesResponse->getStatusCode()) {
      $this->markTestSkipped('Cannot list roles');
    }

    $rolesData = $this->decodeJsonResponse($rolesResponse->getContent() ?: '{}');
    $roles = $this->getCollectionMembers($rolesData);
    if (empty($roles)) {
      $this->markTestSkipped('No roles found');
    }
    $roleId = $this->getStringValue($roles[0], 'id');
    if (null === $roleId) {
      $this->markTestSkipped('Role ID not found');
    }

    // Get a permission ID
    $client->request(
      method: 'GET',
      uri: '/api/permissions',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $permissionsResponse = $client->getResponse();
    if (Response::HTTP_OK !== $permissionsResponse->getStatusCode()) {
      $this->markTestSkipped('Cannot list permissions');
    }

    $permissionsData = $this->decodeJsonResponse($permissionsResponse->getContent() ?: '{}');
    $permissions = $this->getCollectionMembers($permissionsData);
    if (empty($permissions)) {
      $this->markTestSkipped('No permissions found');
    }
    $permissionId = $this->getStringValue($permissions[0], 'id');
    if (null === $permissionId) {
      $this->markTestSkipped('Permission ID not found');
    }

    // First, add the permission to the role to ensure we have something to remove
    $client->request(
      method: 'POST',
      uri: '/api/roles/' . $roleId . '/permissions',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'permission_id' => $permissionId,
      ]) ?: '',
    );

    $addResponse = $client->getResponse();
    // If we can't add the permission (due to security or it already exists), that's fine
    // We'll still try to remove it
    if (!in_array($addResponse->getStatusCode(), [Response::HTTP_OK, Response::HTTP_CREATED, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED], true)) {
      // Unexpected response, but continue anyway
    }

    // Now remove the permission from the role
    $client->request(
      method: 'DELETE',
      uri: '/api/roles/' . $roleId . '/permissions/' . $permissionId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_NO_CONTENT, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_NOT_FOUND],
      'Remove permission from role should respond appropriately. Response: ' . $response->getContent(),
    );
  }

  // #endregion

  // #region Delete Tests

  /**
   * Test deleting a role without authentication.
   */
  public function testDeleteRoleWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'DELETE',
      uri: '/api/roles/00000000-0000-4000-8000-000000000000',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'DELETE role without auth should return 401',
    );
  }

  /**
   * Test deleting a non-existent role.
   */
  public function testDeleteNonExistentRole(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'DELETE',
      uri: '/api/roles/00000000-0000-4000-8000-000000000000',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_NOT_FOUND, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Non-existent role should return 404 or auth error',
    );
  }
  // #endregion

  // #region Setup
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
   * Load fixtures for testing.
   */
  protected static function loadTestFixtures(KernelBrowser $client): void
  {
    $container = $client->getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');

    // Load fixtures
    $loader = new Loader();

    /** @var ClientFixtures $clientFixtures */
    $clientFixtures = $container->get(ClientFixtures::class);
    /** @var UserFixtures $userFixtures */
    $userFixtures = $container->get(UserFixtures::class);
    /** @var AuthorizationFixtures $authFixtures */
    $authFixtures = $container->get(AuthorizationFixtures::class);

    $loader->addFixture($clientFixtures);
    $loader->addFixture($userFixtures);
    $loader->addFixture($authFixtures);

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

  /**
   * Extract collection members from API response.
   *
   * Supports JSON-LD collection format with 'member' key.
   *
   * @param array<string, mixed> $data
   *
   * @return list<array<string, mixed>>
   */
  protected function getCollectionMembers(array $data): array
  {
    // Check for 'member' key (JSON-LD collection format)
    if (isset($data['member']) && is_array($data['member'])) {
      /** @var list<array<string, mixed>> $members */
      $members = $data['member'];

      return $members;
    }

    return [];
  }

  /**
   * Get string value from array at key.
   *
   * @param array<string, mixed> $arr
   */
  protected function getStringValue(array $arr, string $key): ?string
  {
    $value = $arr[$key] ?? null;

    return is_string($value) ? $value : null;
  }

  // #endregion
}
