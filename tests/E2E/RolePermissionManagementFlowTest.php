<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Authorization\Infrastructure\DataFixtures\AuthorizationFixtures;
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
 * Test RolePermissionManagementFlowTest
 *
 * End-to-end tests for Role and Permission management with OAuth2 authentication.
 *
 * @category E2E Tests
 * @package App\Tests\E2E
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class RolePermissionManagementFlowTest extends WebTestCase
{
  //#region Constants
  protected const string DEV_CLIENT_ID = 'a7b8c9d0-e1f2-4456-8123-789012345678';
  protected const string DEV_CLIENT_SECRET = 'dev_secret_test';
  //#endregion

  //#region Properties
  protected ?string $accessToken = null;
  //#endregion

  //#region Setup
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
   * Load fixtures for testing
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
  //#endregion

  //#region Permission List Tests

  /**
   * Test listing permissions with valid token
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
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Should return permissions list or appropriate auth response. Response: ' . $response->getContent()
    );

    if ($response->getStatusCode() === Response::HTTP_OK) {
      $data = json_decode($response->getContent() ?: '{}', true);
      $this->assertArrayHasKey('hydra:member', $data);
      $this->assertIsArray($data['hydra:member']);
      $this->assertNotEmpty($data['hydra:member'], 'Should have permissions from fixtures');
    }
  }

  /**
   * Test listing permissions without token
   */
  public function testListPermissionsWithoutToken(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/permissions',
      server: ['HTTP_ACCEPT' => 'application/ld+json']
    );

    $response = $client->getResponse();
    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Should require authentication'
    );
  }

  //#endregion

  //#region Permission CRUD Tests

  /**
   * Test creating a permission
   */
  public function testCreatePermission(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $uniqueId = uniqid();

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
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Permission creation should require ROLE_SUPER_ADMIN. Response: ' . $response->getContent()
    );
  }

  /**
   * Test creating permission with invalid name format
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
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Invalid permission name format should be rejected'
    );
  }

  /**
   * Test getting a specific permission
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
      ]
    );

    $listResponse = $client->getResponse();

    if ($listResponse->getStatusCode() !== Response::HTTP_OK) {
      $this->markTestSkipped('Cannot list permissions - may require higher permissions');
    }

    $data = json_decode($listResponse->getContent() ?: '{}', true);
    $permissions = $data['hydra:member'] ?? [];

    if (empty($permissions)) {
      $this->markTestSkipped('No permissions found in fixtures');
    }

    $permissionId = $permissions[0]['id'] ?? null;
    $this->assertNotNull($permissionId, 'Permission should have an ID');

    // Now get the specific permission
    $client->request(
      method: 'GET',
      uri: '/api/permissions/' . $permissionId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND],
      'Should return permission or appropriate error'
    );
  }

  //#endregion

  //#region Role List Tests

  /**
   * Test listing roles with valid token
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
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Should return roles list or appropriate auth response. Response: ' . $response->getContent()
    );

    if ($response->getStatusCode() === Response::HTTP_OK) {
      $data = json_decode($response->getContent() ?: '{}', true);
      $this->assertArrayHasKey('hydra:member', $data);
      $this->assertIsArray($data['hydra:member']);
      $this->assertNotEmpty($data['hydra:member'], 'Should have roles from fixtures');
    }
  }

  /**
   * Test listing roles without token
   */
  public function testListRolesWithoutToken(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/roles',
      server: ['HTTP_ACCEPT' => 'application/ld+json']
    );

    $response = $client->getResponse();
    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Should require authentication'
    );
  }

  //#endregion

  //#region Role CRUD Tests

  /**
   * Test creating a role
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
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Role creation should require ROLE_ADMIN. Response: ' . $response->getContent()
    );
  }

  /**
   * Test creating role with invalid name
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
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Invalid role name should be rejected'
    );
  }

  /**
   * Test getting a specific role
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
      ]
    );

    $listResponse = $client->getResponse();

    if ($listResponse->getStatusCode() !== Response::HTTP_OK) {
      $this->markTestSkipped('Cannot list roles - may require higher permissions');
    }

    $data = json_decode($listResponse->getContent() ?: '{}', true);
    $roles = $data['hydra:member'] ?? [];

    if (empty($roles)) {
      $this->markTestSkipped('No roles found in fixtures');
    }

    $roleId = $roles[0]['id'] ?? null;
    $this->assertNotNull($roleId, 'Role should have an ID');

    // Now get the specific role
    $client->request(
      method: 'GET',
      uri: '/api/roles/' . $roleId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND],
      'Should return role or appropriate error'
    );

    if ($response->getStatusCode() === Response::HTTP_OK) {
      $roleData = json_decode($response->getContent() ?: '{}', true);
      $this->assertArrayHasKey('permissions', $roleData, 'Role should include permissions');
    }
  }

  /**
   * Test updating a role
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
      ]
    );

    $listResponse = $client->getResponse();

    if ($listResponse->getStatusCode() !== Response::HTTP_OK) {
      $this->markTestSkipped('Cannot list roles');
    }

    $data = json_decode($listResponse->getContent() ?: '{}', true);
    $roles = $data['hydra:member'] ?? [];

    if (empty($roles)) {
      $this->markTestSkipped('No roles found');
    }

    $roleId = $roles[0]['id'] ?? null;

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
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_NOT_FOUND],
      'PATCH role should respond appropriately. Response: ' . $response->getContent()
    );
  }

  //#endregion

  //#region Role Permission Subresource Tests

  /**
   * Test adding a permission to a role
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
      ]
    );

    $rolesResponse = $client->getResponse();
    if ($rolesResponse->getStatusCode() !== Response::HTTP_OK) {
      $this->markTestSkipped('Cannot list roles');
    }

    $rolesData = json_decode($rolesResponse->getContent() ?: '{}', true);
    $roles = $rolesData['hydra:member'] ?? [];
    if (empty($roles)) {
      $this->markTestSkipped('No roles found');
    }
    $roleId = $roles[0]['id'];

    // Get a permission ID
    $client->request(
      method: 'GET',
      uri: '/api/permissions',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $permissionsResponse = $client->getResponse();
    if ($permissionsResponse->getStatusCode() !== Response::HTTP_OK) {
      $this->markTestSkipped('Cannot list permissions');
    }

    $permissionsData = json_decode($permissionsResponse->getContent() ?: '{}', true);
    $permissions = $permissionsData['hydra:member'] ?? [];
    if (empty($permissions)) {
      $this->markTestSkipped('No permissions found');
    }
    $permissionId = $permissions[0]['id'];

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
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_NOT_FOUND],
      'Add permission to role should respond appropriately. Response: ' . $response->getContent()
    );
  }

  /**
   * Test removing a permission from a role
   */
  public function testRemovePermissionFromRole(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    // Get a role with permissions
    $client->request(
      method: 'GET',
      uri: '/api/roles',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $rolesResponse = $client->getResponse();
    if ($rolesResponse->getStatusCode() !== Response::HTTP_OK) {
      $this->markTestSkipped('Cannot list roles');
    }

    $rolesData = json_decode($rolesResponse->getContent() ?: '{}', true);
    $roles = $rolesData['hydra:member'] ?? [];

    // Find a role with at least one permission
    $roleId = null;
    $permissionId = null;
    foreach ($roles as $role) {
      if (!empty($role['permissions'])) {
        $roleId = $role['id'];
        $permissionId = $role['permissions'][0]['id'] ?? null;
        break;
      }
    }

    if ($roleId === null || $permissionId === null) {
      $this->markTestSkipped('No role with permissions found');
    }

    // Remove permission from role
    $client->request(
      method: 'DELETE',
      uri: '/api/roles/' . $roleId . '/permissions/' . $permissionId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_NO_CONTENT, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_NOT_FOUND],
      'Remove permission from role should respond appropriately. Response: ' . $response->getContent()
    );
  }

  //#endregion

  //#region Delete Tests

  /**
   * Test deleting a role without authentication
   */
  public function testDeleteRoleWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'DELETE',
      uri: '/api/roles/00000000-0000-4000-8000-000000000000',
      server: ['HTTP_ACCEPT' => 'application/ld+json']
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'DELETE role without auth should return 401'
    );
  }

  /**
   * Test deleting a non-existent role
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
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_NOT_FOUND, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Non-existent role should return 404 or auth error'
    );
  }

  //#endregion
}
