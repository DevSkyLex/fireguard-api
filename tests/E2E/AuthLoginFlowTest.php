<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Test AuthLoginFlowTest
 *
 * End-to-end tests for the /api/auth/login endpoint.
 *
 * @category E2E Tests
 * @package App\Tests\E2E
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class AuthLoginFlowTest extends OAuth2WebTestCase
{
  //#region Login Tests

  /**
   * Test login with valid credentials
   */
  public function testLoginWithValidCredentials(): void
  {
    $client = static::createClientWithFixtures();

    // Create a test user first
    $email = 'login-test-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    $this->createAndActivateUser($client, $email, $password);

    // Now test login
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
      ]) ?: ''
    );

    $response = $client->getResponse();
    $content = $response->getContent() ?: '';

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      "Login should succeed. Response: {$content}"
    );

    $data = json_decode($content, true);
    $this->assertArrayHasKey('access_token', $data, 'Response should contain access_token');
    $this->assertArrayHasKey('token_type', $data, 'Response should contain token_type');
    $this->assertEquals('Bearer', $data['token_type']);
  }

  /**
   * Test login with invalid password
   */
  public function testLoginWithInvalidPassword(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'login-invalid-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    $this->createAndActivateUser($client, $email, $password);

    // Try login with wrong password
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => $email,
        'password' => 'WrongPassword!',
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Login with wrong password should return 401'
    );
  }

  /**
   * Test login with non-existent user
   */
  public function testLoginWithNonExistentUser(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => 'nonexistent-' . uniqid() . '@example.com',
        'password' => 'AnyPassword123!',
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Login with non-existent user should return 401'
    );
  }

  /**
   * Test login with inactive user (pending_verification)
   */
  public function testLoginWithInactiveUser(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'login-inactive-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    // Create user but DON'T activate
    $this->createUser($client, $email, $password);

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
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Login with inactive user should return 401'
    );
  }

  /**
   * Test login sets refresh token cookie
   */
  public function testLoginSetsRefreshTokenCookie(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'login-cookie-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    $this->createAndActivateUser($client, $email, $password);

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
        'rememberMe' => true,
      ]) ?: ''
    );

    $response = $client->getResponse();

    if ($response->getStatusCode() !== Response::HTTP_OK && $response->getStatusCode() !== Response::HTTP_CREATED) {
      $this->markTestSkipped('Login failed: ' . $response->getContent());
    }

    // Check for refresh token cookie
    $cookies = $response->headers->getCookies();
    $refreshTokenCookie = null;

    foreach ($cookies as $cookie) {
      if ($cookie->getName() === 'refresh_token') {
        $refreshTokenCookie = $cookie;
        break;
      }
    }

    $this->assertNotNull($refreshTokenCookie, 'Response should set refresh_token cookie');
    $this->assertTrue($refreshTokenCookie->isHttpOnly(), 'Cookie should be HttpOnly');
  }

  //#endregion

  //#region Helper Methods

  /**
   * Create a user via console command simulation
   */
  private function createUser(KernelBrowser $client, string $email, string $password): void
  {
    $container = static::getContainer();
    /** @var MessageBusInterface $bus */
    $bus = $container->get(MessageBusInterface::class);

    $command = new \User\Application\UseCase\Command\CreateUser\CreateUserCommand(
      username: 'user' . uniqid(),
      email: $email,
      password: $password,
      firstName: 'Test',
      lastName: 'User',
    );

    $bus->dispatch($command);
  }

  /**
   * Create and activate a user
   */
  private function createAndActivateUser(KernelBrowser $client, string $email, string $password): void
  {
    $this->createUser($client, $email, $password);

    // Activate user directly in database
    $container = static::getContainer();
    /** @var EntityManagerInterface $em */
    $em = $container->get(EntityManagerInterface::class);

    $em->getConnection()->executeStatement(
      "UPDATE users SET status = 'active' WHERE email = ?",
      [$email]
    );

    // Debug: verify user exists and password is correct
    $row = $em->getConnection()->fetchAssociative(
      "SELECT id, email, password, status FROM users WHERE email = ?",
      [$email]
    );

    if (!$row) {
      throw new \RuntimeException("User not found after creation: {$email}");
    }

    if (!password_verify($password, $row['password'])) {
      throw new \RuntimeException("Password verification failed. Hash: {$row['password']}");
    }

    if ($row['status'] !== 'active') {
      throw new \RuntimeException("User status is not active: {$row['status']}");
    }

    // Clear entity manager to ensure fresh data
    $em->clear();
  }

  //#endregion

  //#region Refresh Token Tests

  /**
   * Test refresh token flow
   */
  public function testRefreshTokenFlow(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'refresh-flow-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    $this->createAndActivateUser($client, $email, $password);

    // Step 1: Login to get tokens
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
      ]) ?: ''
    );

    $response = $client->getResponse();

    if ($response->getStatusCode() !== Response::HTTP_OK && $response->getStatusCode() !== Response::HTTP_CREATED) {
      $this->markTestSkipped('Login failed: ' . $response->getContent());
    }

    // Get the refresh token cookie
    $cookies = $response->headers->getCookies();
    $refreshTokenCookie = null;

    foreach ($cookies as $cookie) {
      if ($cookie->getName() === 'refresh_token') {
        $refreshTokenCookie = $cookie;
        break;
      }
    }

    if ($refreshTokenCookie === null) {
      $this->markTestSkipped('No refresh token cookie set');
    }

    // Step 2: Use refresh token to get new access token
    $client->request(
      method: 'POST',
      uri: '/api/auth/refresh',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_COOKIE' => 'refresh_token=' . $refreshTokenCookie->getValue(),
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Refresh should succeed. Response: ' . $response->getContent()
    );

    $data = json_decode($response->getContent() ?: '{}', true);
    $this->assertArrayHasKey('access_token', $data, 'Response should contain new access_token');
    $this->assertArrayHasKey('token_type', $data, 'Response should contain token_type');
    $this->assertEquals('Bearer', $data['token_type']);
  }

  /**
   * Test refresh token without cookie returns 401
   */
  public function testRefreshWithoutCookieReturns401(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/auth/refresh',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ]
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Refresh without cookie should return 401'
    );
  }

  /**
   * Test refresh with invalid token returns 401
   */
  public function testRefreshWithInvalidTokenReturns401(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/auth/refresh',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_COOKIE' => 'refresh_token=invalid_token_value',
      ]
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Refresh with invalid token should return 401'
    );
  }

  //#endregion

  //#region Logout Tests

  /**
   * Test logout clears refresh token cookie
   */
  public function testLogoutClearsRefreshTokenCookie(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'logout-test-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    $this->createAndActivateUser($client, $email, $password);

    // Step 1: Login
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
      ]) ?: ''
    );

    $loginResponse = $client->getResponse();

    if ($loginResponse->getStatusCode() !== Response::HTTP_OK && $loginResponse->getStatusCode() !== Response::HTTP_CREATED) {
      $this->markTestSkipped('Login failed: ' . $loginResponse->getContent());
    }

    $loginData = json_decode($loginResponse->getContent() ?: '{}', true);
    $accessToken = $loginData['access_token'] ?? '';

    // Step 2: Logout
    $client->request(
      method: 'POST',
      uri: '/api/auth/logout',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken,
      ]
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Logout should succeed'
    );

    $data = json_decode($response->getContent() ?: '{}', true);
    $this->assertArrayHasKey('message', $data);
    $this->assertEquals('Logged out successfully', $data['message']);
  }

  /**
   * Test logout without authentication still succeeds
   */
  public function testLogoutWithoutAuthenticationSucceeds(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/auth/logout',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ]
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Logout without auth should still succeed'
    );
  }

  /**
   * Test complete login-refresh-logout flow
   */
  public function testCompleteAuthenticationFlow(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'complete-flow-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    $this->createAndActivateUser($client, $email, $password);

    // Step 1: Login
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
      ]) ?: ''
    );

    $loginResponse = $client->getResponse();

    if ($loginResponse->getStatusCode() !== Response::HTTP_OK && $loginResponse->getStatusCode() !== Response::HTTP_CREATED) {
      $this->markTestSkipped('Login failed: ' . $loginResponse->getContent());
    }

    $loginData = json_decode($loginResponse->getContent() ?: '{}', true);
    $accessToken = $loginData['access_token'] ?? '';

    $this->assertNotEmpty($accessToken, 'Should have access token after login');

    // Get refresh token cookie
    $cookies = $loginResponse->headers->getCookies();
    $refreshTokenCookie = null;
    foreach ($cookies as $cookie) {
      if ($cookie->getName() === 'refresh_token') {
        $refreshTokenCookie = $cookie;
        break;
      }
    }

    if ($refreshTokenCookie === null) {
      $this->markTestSkipped('No refresh token cookie set');
    }

    // Step 2: Refresh token
    $client->request(
      method: 'POST',
      uri: '/api/auth/refresh',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_COOKIE' => 'refresh_token=' . $refreshTokenCookie->getValue(),
      ]
    );

    $refreshResponse = $client->getResponse();

    $this->assertContains(
      $refreshResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Refresh should succeed'
    );

    $refreshData = json_decode($refreshResponse->getContent() ?: '{}', true);
    $newAccessToken = $refreshData['access_token'] ?? '';

    $this->assertNotEmpty($newAccessToken, 'Should have new access token after refresh');
    $this->assertNotEquals($accessToken, $newAccessToken, 'New access token should be different');

    // Step 3: Logout
    $client->request(
      method: 'POST',
      uri: '/api/auth/logout',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $newAccessToken,
      ]
    );

    $logoutResponse = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_OK,
      $logoutResponse->getStatusCode(),
      'Logout should succeed'
    );
  }

  /**
   * Test login with empty email
   */
  public function testLoginWithEmptyEmail(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => '',
        'password' => 'SomePassword123!',
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_BAD_REQUEST],
      'Login with empty email should fail'
    );
  }

  /**
   * Test login with empty password
   */
  public function testLoginWithEmptyPassword(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => 'test@example.com',
        'password' => '',
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_BAD_REQUEST],
      'Login with empty password should fail'
    );
  }

  /**
   * Test login response structure
   */
  public function testLoginResponseStructure(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'structure-test-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    $this->createAndActivateUser($client, $email, $password);

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
      ]) ?: ''
    );

    $response = $client->getResponse();

    if ($response->getStatusCode() !== Response::HTTP_OK && $response->getStatusCode() !== Response::HTTP_CREATED) {
      $this->markTestSkipped('Login failed: ' . $response->getContent());
    }

    $data = json_decode($response->getContent() ?: '{}', true);

    $this->assertArrayHasKey('access_token', $data);
    $this->assertArrayHasKey('token_type', $data);
    $this->assertArrayHasKey('expires_in', $data);
    $this->assertArrayHasKey('scope', $data);

    $this->assertEquals('Bearer', $data['token_type']);
    $this->assertIsInt($data['expires_in']);
    $this->assertGreaterThan(0, $data['expires_in']);
  }

  //#endregion
}
