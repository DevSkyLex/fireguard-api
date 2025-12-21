<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

use function is_string;
use function json_encode;
use function uniqid;

/**
 * Test AuthLoginFlowTest.
 *
 * End-to-end tests for the /api/auth/login endpoint.
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class AuthLoginFlowTest extends OAuth2WebTestCase
{
  // #region Login Tests
  /**
   * Method testLoginWithValidCredentials.
   *
   * Test login with valid credentials
   *
   * @return void None
   */
  public function testLoginWithValidCredentials(): void
  {
    $client = static::createClientWithFixtures();

    // Create a test user first
    $email = 'login-test-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    $this->createAndActivateUser(
      client: $client,
      email: $email,
      password: $password,
    );

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
      ]) ?: '',
    );

    $response = $client->getResponse();
    $content = $response->getContent() ?: '';

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      "Login should succeed. Response: {$content}",
    );

    $data = $this->decodeJsonResponse($content);
    $this->assertArrayHasKey(
      key: 'access_token',
      array: $data,
      message: 'Response should contain access_token',
    );

    $this->assertArrayHasKey(
      key: 'token_type',
      array: $data,
      message: 'Response should contain token_type',
    );

    $this->assertEquals(
      expected: 'Bearer',
      actual: $data['token_type'],
      message: 'Response should contain token_type',
    );
  }

  /**
   * Method testLoginWithInvalidPassword.
   *
   * Test login with invalid password
   *
   * @return void None
   */
  public function testLoginWithInvalidPassword(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'login-invalid-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    $this->createAndActivateUser(
      client: $client,
      email: $email,
      password: $password,
    );

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
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertEquals(
      expected: Response::HTTP_UNAUTHORIZED,
      actual: $response->getStatusCode(),
      message: 'Login with wrong password should return 401',
    );
  }

  /**
   * Method testLoginWithNonExistentUser.
   *
   * Test login with non-existent user
   *
   * @return void None
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
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertEquals(
      expected: Response::HTTP_UNAUTHORIZED,
      actual: $response->getStatusCode(),
      message: 'Login with non-existent user should return 401',
    );
  }

  /**
   * Method testLoginWithInactiveUser.
   *
   * Test login with inactive user (pending_verification)
   *
   * @return void None
   */
  public function testLoginWithInactiveUser(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'login-inactive-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    // Create user but DON'T activate
    $this->createUser(
      client: $client,
      email: $email,
      password: $password,
    );

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

    $this->assertEquals(
      expected: Response::HTTP_UNAUTHORIZED,
      actual: $response->getStatusCode(),
      message: 'Login with inactive user should return 401',
    );
  }

  /**
   * Method testLoginSetsRefreshTokenCookie.
   *
   * Test login sets refresh token cookie
   *
   * @return void None
   */
  public function testLoginSetsRefreshTokenCookie(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'login-cookie-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    $this->createAndActivateUser(
      client: $client,
      email: $email,
      password: $password,
    );

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
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      needle: $response->getStatusCode(),
      haystack: [Response::HTTP_OK, Response::HTTP_CREATED],
      message: 'Login should succeed. Response: ' . $response->getContent(),
    );

    // Check for refresh token cookie
    $cookies = $response->headers->getCookies();
    $refreshTokenCookie = null;

    foreach ($cookies as $cookie) {
      if ('refresh_token' === $cookie->getName()) {
        $refreshTokenCookie = $cookie;
        break;
      }
    }

    $this->assertNotNull(
      actual: $refreshTokenCookie,
      message: 'Response should set refresh_token cookie',
    );

    $this->assertTrue(
      condition: $refreshTokenCookie->isHttpOnly(),
      message: 'Cookie should be HttpOnly',
    );
  }

  /**
   * Method testRefreshTokenFlow.
   *
   * Test refresh token flow
   *
   * @return void None
   */
  public function testRefreshTokenFlow(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'refresh-flow-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    $this->createAndActivateUser(
      client: $client,
      email: $email,
      password: $password,
    );

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
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      needle: $response->getStatusCode(),
      haystack: [Response::HTTP_OK, Response::HTTP_CREATED],
      message: 'Login should succeed. Response: ' . $response->getContent(),
    );

    // Get the refresh token cookie
    $cookies = $response->headers->getCookies();
    $refreshTokenCookie = null;

    foreach ($cookies as $cookie) {
      if ('refresh_token' === $cookie->getName()) {
        $refreshTokenCookie = $cookie;
        break;
      }
    }

    $this->assertNotNull(
      actual: $refreshTokenCookie,
      message: 'Refresh token cookie should be set',
    );

    // Step 2: Use refresh token to get new access token
    $client->request(
      method: 'POST',
      uri: '/api/auth/refresh',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_COOKIE' => 'refresh_token=' . $refreshTokenCookie->getValue(),
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      needle: $response->getStatusCode(),
      haystack: [Response::HTTP_OK, Response::HTTP_CREATED],
      message: 'Refresh should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertArrayHasKey(
      key: 'access_token',
      array: $data,
      message: 'Response should contain new access_token',
    );

    $this->assertArrayHasKey(
      key: 'token_type',
      array: $data,
      message: 'Response should contain token_type',
    );

    $this->assertEquals(
      expected: 'Bearer',
      actual: $data['token_type'],
      message: 'Response should contain token_type',
    );
  }

  /**
   * Method testRefreshWithoutCookieReturns401.
   *
   * Test refresh token without cookie
   * returns 401
   *
   * @return void None
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
      ],
    );

    $response = $client->getResponse();

    $this->assertEquals(
      expected: Response::HTTP_UNAUTHORIZED,
      actual: $response->getStatusCode(),
      message: 'Refresh without cookie should return 401',
    );
  }

  /**
   * Method testRefreshWithInvalidTokenReturns401.
   *
   * Test refresh with invalid token
   * returns 401
   *
   * @return void None
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
      ],
    );

    $response = $client->getResponse();

    $this->assertEquals(
      expected: Response::HTTP_UNAUTHORIZED,
      actual: $response->getStatusCode(),
      message: 'Refresh with invalid token should return 401',
    );
  }

  /**
   * Method testLogoutClearsRefreshTokenCookie.
   *
   * Test logout clears refresh token cookie
   *
   * @return void None
   */
  public function testLogoutClearsRefreshTokenCookie(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'logout-test-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    $this->createAndActivateUser(
      client: $client,
      email: $email,
      password: $password,
    );

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
      ]) ?: '',
    );

    $loginResponse = $client->getResponse();

    $this->assertContains(
      needle: $loginResponse->getStatusCode(),
      haystack: [Response::HTTP_OK, Response::HTTP_CREATED],
      message: 'Login should succeed. Response: ' . $loginResponse->getContent(),
    );

    $loginData = $this->decodeJsonResponse($loginResponse->getContent() ?: '{}');
    $tokenValue = $loginData['access_token'] ?? '';
    $accessToken = is_string($tokenValue) ? $tokenValue : '';

    // Step 2: Logout
    $client->request(
      method: 'POST',
      uri: '/api/auth/logout',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken,
      ],
    );

    $response = $client->getResponse();

    $this->assertEquals(
      expected: Response::HTTP_OK,
      actual: $response->getStatusCode(),
      message: 'Logout should succeed',
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertArrayHasKey(
      key: 'message',
      array: $data,
      message: 'Logout should succeed',
    );

    $this->assertEquals(
      expected: 'Logged out successfully',
      actual: $data['message'],
      message: 'Logout should succeed',
    );
  }

  /**
   * Method testLogoutWithoutAuthenticationSucceeds.
   *
   * Test logout without authentication
   * still succeeds
   *
   * @return void None
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
      ],
    );

    $response = $client->getResponse();

    $this->assertEquals(
      expected: Response::HTTP_OK,
      actual: $response->getStatusCode(),
      message: 'Logout without auth should still succeed',
    );
  }

  /**
   * Method testCompleteAuthenticationFlow.
   *
   * Test complete login-refresh-logout flow
   *
   * @return void None
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
      ]) ?: '',
    );

    $loginResponse = $client->getResponse();

    $this->assertContains(
      needle: $loginResponse->getStatusCode(),
      haystack: [Response::HTTP_OK, Response::HTTP_CREATED],
      message: 'Login should succeed. Response: ' . $loginResponse->getContent(),
    );

    $loginData = $this->decodeJsonResponse($loginResponse->getContent() ?: '{}');

    $tokenValue = $loginData['access_token'] ?? '';
    $accessToken = is_string($tokenValue) ? $tokenValue : '';

    $this->assertNotEmpty(
      actual: $accessToken,
      message: 'Should have access token after login',
    );

    // Get refresh token cookie
    $cookies = $loginResponse->headers->getCookies();
    $refreshTokenCookie = null;
    foreach ($cookies as $cookie) {
      if ('refresh_token' === $cookie->getName()) {
        $refreshTokenCookie = $cookie;
        break;
      }
    }

    $this->assertNotNull(
      actual: $refreshTokenCookie,
      message: 'Refresh token cookie should be set',
    );

    // Step 2: Refresh token
    $client->request(
      method: 'POST',
      uri: '/api/auth/refresh',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_COOKIE' => 'refresh_token=' . $refreshTokenCookie->getValue(),
      ],
    );

    $refreshResponse = $client->getResponse();

    $this->assertContains(
      needle: $refreshResponse->getStatusCode(),
      haystack: [Response::HTTP_OK, Response::HTTP_CREATED],
      message: 'Refresh should succeed',
    );

    $refreshData = $this->decodeJsonResponse($refreshResponse->getContent() ?: '{}');

    $newTokenValue = $refreshData['access_token'] ?? '';
    $newAccessToken = is_string($newTokenValue) ? $newTokenValue : '';

    $this->assertNotEmpty(
      actual: $newAccessToken,
      message: 'Should have new access token after refresh',
    );

    $this->assertNotEquals(
      expected: $accessToken,
      actual: $newAccessToken,
      message: 'New access token should be different',
    );

    // Step 3: Logout
    $client->request(
      method: 'POST',
      uri: '/api/auth/logout',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $newAccessToken,
      ],
    );

    $logoutResponse = $client->getResponse();

    $this->assertEquals(
      expected: Response::HTTP_OK,
      actual: $logoutResponse->getStatusCode(),
      message: 'Logout should succeed',
    );
  }

  /**
   * Method testLoginWithEmptyEmail.
   *
   * Test login with empty email
   *
   * @return void None
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
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      needle: $response->getStatusCode(),
      haystack: [Response::HTTP_UNAUTHORIZED, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_BAD_REQUEST],
      message: 'Login with empty email should fail',
    );
  }

  /**
   * Method testLoginWithEmptyPassword.
   *
   * Test login with empty password
   *
   * @return void None
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
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      needle: $response->getStatusCode(),
      haystack: [Response::HTTP_UNAUTHORIZED, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_BAD_REQUEST],
      message: 'Login with empty password should fail',
    );
  }

  /**
   * Method testLoginResponseStructure.
   *
   * Test login response structure
   *
   * @return void None
   */
  public function testLoginResponseStructure(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'structure-test-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';

    $this->createAndActivateUser(
      client: $client,
      email: $email,
      password: $password,
    );

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
      needle: $response->getStatusCode(),
      haystack: [Response::HTTP_OK, Response::HTTP_CREATED],
      message: 'Login should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');

    $this->assertArrayHasKey('access_token', $data);
    $this->assertArrayHasKey('token_type', $data);
    $this->assertArrayHasKey('expires_in', $data);
    $this->assertArrayHasKey('scope', $data);

    $this->assertEquals('Bearer', $data['token_type']);
    $this->assertIsInt($data['expires_in']);
    $this->assertGreaterThan(0, $data['expires_in']);
  }

  // #endregion
}
