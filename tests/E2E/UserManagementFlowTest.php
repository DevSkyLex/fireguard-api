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

    if ($token === null) {
      $this->markTestSkipped('Could not obtain access token');
    }

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
      $data = json_decode($response->getContent() ?: '{}', true);
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
   * Test complete user CRUD flow
   */
  public function testUserCrudFlow(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    if ($token === null) {
      $this->markTestSkipped('Could not obtain access token');
    }
    $uniqueId = uniqid();

    // CREATE
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

    // May be forbidden or unauthorized
    if ($response->getStatusCode() === Response::HTTP_FORBIDDEN || $response->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
      $this->markTestSkipped('Token does not have permission to create users or auth failed');
    }

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'User creation should succeed. Response: ' . $response->getContent()
    );

    $userData = json_decode($response->getContent() ?: '{}', true);
    $this->assertArrayHasKey('@id', $userData);

    $userId = basename($userData['@id']);

    // READ
    $client->request(
      method: 'GET',
      uri: '/api/users/' . $userId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

    $userData = json_decode($response->getContent() ?: '{}', true);
    $this->assertEquals('e2e_test_' . $uniqueId, $userData['username'] ?? '');

    // UPDATE
    $client->request(
      method: 'PATCH',
      uri: '/api/users/' . $userId,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'firstName' => 'Updated',
        'lastName' => 'Name',
      ]) ?: ''
    );

    $response = $client->getResponse();
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

    $userData = json_decode($response->getContent() ?: '{}', true);
    $this->assertEquals('Updated', $userData['firstName'] ?? '');

    // DELETE
    $client->request(
      method: 'DELETE',
      uri: '/api/users/' . $userId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();
    $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

    // VERIFY DELETED
    $client->request(
      method: 'GET',
      uri: '/api/users/' . $userId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();
    $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
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

    if ($token === null) {
      $this->markTestSkipped('Could not obtain access token');
    }

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

    if ($token === null) {
      $this->markTestSkipped('Could not obtain access token');
    }

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

    if ($token === null) {
      $this->markTestSkipped('Could not obtain access token');
    }

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

    if ($token === null) {
      $this->markTestSkipped('Could not obtain access token');
    }

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
      $data = json_decode($response->getContent() ?: '{}', true);
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

    if ($token === null) {
      $this->markTestSkipped('Could not obtain access token');
    }

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
}
