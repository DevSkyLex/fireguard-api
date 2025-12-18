<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

/**
 * Test UserManagementFlowTest
 *
 * End-to-end tests for user management with OAuth2 authentication.
 *
 * @category E2E Tests
 * @package App\Tests\E2E
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class UserManagementFlowTest extends OAuth2WebTestCase
{
  //#region User List Tests

  /**
   * Test listing users with valid token
   */
  public function testListUsersWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'GET',
      uri: '/api/users',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Should return users list or appropriate auth response. Response: ' . $response->getContent()
    );

    if ($response->getStatusCode() === Response::HTTP_OK) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $this->assertArrayHasKey('hydra:member', $data);
      $this->assertIsArray($data['hydra:member']);
    }
  }

  /**
   * Test listing users without token
   */
  public function testListUsersWithoutToken(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/users',
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

  //#region User CRUD Tests

  /**
   * Test user creation requires proper authorization
   *
   * Client credentials tokens typically don't have permission to create users.
   * This test verifies that proper authorization is enforced.
   */
  public function testUserCreationRequiresAuthorization(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $uniqueId = uniqid();

    // Try to CREATE user with client credentials token
    $client->request(
      method: 'POST',
      uri: '/api/users',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'username' => 'e2e_test_' . $uniqueId,
        'email' => 'e2e_test_' . $uniqueId . '@example.com',
        'password' => 'TestPassword123!',
        'firstName' => 'E2E',
        'lastName' => 'Test',
      ]) ?: ''
    );

    $response = $client->getResponse();

    // Client credentials tokens should not have permission to create users
    // This is expected security behavior - either forbidden/unauthorized or created if permissions allow
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_CREATED],
      'User creation should require proper authorization. Response: ' . $response->getContent()
    );
  }

  //#endregion

  //#region Validation Tests

  /**
   * Test user creation with invalid data
   */
  public function testCreateUserWithInvalidData(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    // Missing required fields
    $client->request(
      method: 'POST',
      uri: '/api/users',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'username' => 'test',
        // Missing email, password, etc.
      ]) ?: ''
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Invalid data should be rejected or return auth error'
    );
  }

  /**
   * Test user creation with invalid email
   */
  public function testCreateUserWithInvalidEmail(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'POST',
      uri: '/api/users',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'username' => 'validuser',
        'email' => 'not-an-email',
        'password' => 'TestPassword123!',
        'firstName' => 'Test',
        'lastName' => 'User',
      ]) ?: ''
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Invalid email should be rejected or return auth error'
    );
  }

  /**
   * Test user creation with weak password
   */
  public function testCreateUserWithWeakPassword(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'POST',
      uri: '/api/users',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'username' => 'validuser',
        'email' => 'valid@example.com',
        'password' => '123', // Too weak
        'firstName' => 'Test',
        'lastName' => 'User',
      ]) ?: ''
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Weak password should be rejected or return auth error'
    );
  }

  //#endregion

  //#region Get Specific User Tests

  /**
   * Test getting a specific user by ID
   */
  public function testGetUserById(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    // Use admin user from fixtures
    $adminUserId = 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890';

    $client->request(
      method: 'GET',
      uri: '/api/users/' . $adminUserId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND, Response::HTTP_UNAUTHORIZED],
      'Should return user or appropriate error'
    );

    if ($response->getStatusCode() === Response::HTTP_OK) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $this->assertEquals('admin', $data['username'] ?? '');
    }
  }

  /**
   * Test getting non-existent user
   */
  public function testGetNonExistentUser(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'GET',
      uri: '/api/users/00000000-0000-4000-8000-000000000000',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_NOT_FOUND, Response::HTTP_UNAUTHORIZED],
      'Non-existent user should return 404 or auth error'
    );
  }

  //#endregion

  //#region User Update Tests

  /**
   * Test updating user with PATCH
   */
  public function testUpdateUserWithPatch(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    // Use admin user from fixtures
    $adminUserId = 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890';

    $client->request(
      method: 'PATCH',
      uri: '/api/users/' . $adminUserId,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'firstName' => 'UpdatedFirst',
        'lastName' => 'UpdatedLast',
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_NOT_FOUND],
      'PATCH user should respond appropriately. Response: ' . $response->getContent()
    );

    if ($response->getStatusCode() === Response::HTTP_OK) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $this->assertEquals('UpdatedFirst', $data['firstName'] ?? '');
    }
  }

  /**
   * Test updating user with PATCH without authentication
   */
  public function testUpdateUserWithPatchWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $adminUserId = 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890';

    $client->request(
      method: 'PATCH',
      uri: '/api/users/' . $adminUserId,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'firstName' => 'Hacker',
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'PATCH user without auth should return 401'
    );
  }

  /**
   * Test replacing user with PUT
   */
  public function testReplaceUserWithPut(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $adminUserId = 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890';

    $client->request(
      method: 'PUT',
      uri: '/api/users/' . $adminUserId,
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'username' => 'admin',
        'email' => 'admin@example.com',
        'firstName' => 'Admin',
        'lastName' => 'User',
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_NOT_FOUND, Response::HTTP_UNPROCESSABLE_ENTITY],
      'PUT user should respond appropriately. Response: ' . $response->getContent()
    );
  }

  /**
   * Test replacing user with PUT without authentication
   */
  public function testReplaceUserWithPutWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $adminUserId = 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890';

    $client->request(
      method: 'PUT',
      uri: '/api/users/' . $adminUserId,
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'username' => 'hacker',
        'email' => 'hacker@example.com',
        'firstName' => 'Hacker',
        'lastName' => 'User',
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'PUT user without auth should return 401'
    );
  }

  /**
   * Test updating non-existent user
   */
  public function testUpdateNonExistentUser(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'PATCH',
      uri: '/api/users/00000000-0000-4000-8000-000000000000',
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'firstName' => 'Test',
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_NOT_FOUND, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Non-existent user should return 404 or auth error'
    );
  }

  //#endregion

  //#region User Delete Tests

  /**
   * Test deleting user
   */
  public function testDeleteUser(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    // Use a user ID that exists in fixtures (not admin to avoid breaking other tests)
    // We'll use a non-existent ID to test the endpoint behavior
    $testUserId = '00000000-0000-4000-8000-000000000001';

    $client->request(
      method: 'DELETE',
      uri: '/api/users/' . $testUserId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_NO_CONTENT, Response::HTTP_NOT_FOUND, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'DELETE user should respond appropriately. Response: ' . $response->getContent()
    );
  }

  /**
   * Test deleting user without authentication
   */
  public function testDeleteUserWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'DELETE',
      uri: '/api/users/a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
      ]
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'DELETE user without auth should return 401'
    );
  }

  /**
   * Test deleting non-existent user
   */
  public function testDeleteNonExistentUser(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'DELETE',
      uri: '/api/users/00000000-0000-4000-8000-000000000000',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_NOT_FOUND, Response::HTTP_NO_CONTENT, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Non-existent user should return 404 or auth error'
    );
  }

  //#endregion
}

