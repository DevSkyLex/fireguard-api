<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

use function json_encode;

/**
 * Test PasswordResetFlowTest.
 *
 * End-to-end tests for password reset flow.
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class PasswordResetFlowTest extends OAuth2WebTestCase
{
  /**
   * Test the complete password reset flow.
   *
   * This test verifies:
   * 1. User can request password reset
   * 2. User receives OTP code (simulated)
   * 3. User can confirm reset with valid code
   * 4. User can login with new password
   * 5. Old password no longer works
   */
  public function testCompletePasswordResetFlow(): void
  {
    $client = static::createClientWithFixtures();

    $testEmail = 'john.doe@example.com';
    $oldPassword = 'SecurePassword123!';
    $newPassword = 'NewSecureP@ss123!';

    // Step 1: Request password reset
    $client->request(
      method: 'POST',
      uri: '/api/auth/password/reset/request',
      server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
      ],
      content: json_encode([
        'email' => $testEmail,
      ]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertEquals(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Password reset request should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertTrue($data['success'] ?? false, 'Request should return success');
    $this->assertArrayHasKey('message', $data);

    // Step 2: In a real scenario, we would retrieve the OTP code from email
    // For testing, we need to extract it from the database or use a test hook
    // Since we don't have direct access, we'll simulate by finding the OTP in DB
    // This is a limitation of E2E tests - in integration tests we could mock the email

    // For now, we'll test that the endpoint structure works
    // A complete flow would require test email integration or database inspection

    $this->markTestIncomplete(
      'Complete flow requires OTP code extraction from test database or email mock. ' .
      'The request endpoint is functional. Implement OTP retrieval for full E2E test.',
    );
  }

  /**
   * Test password reset request with non-existent email.
   *
   * Should return success to prevent user enumeration.
   */
  public function testPasswordResetRequestWithNonExistentEmail(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/auth/password/reset/request',
      server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
      ],
      content: json_encode([
        'email' => 'nonexistent@example.com',
      ]) ?: '',
    );

    $response = $client->getResponse();

    // Should return 200 to prevent user enumeration
    $this->assertEquals(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Should return success even for non-existent email',
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertTrue($data['success'] ?? false);
  }

  /**
   * Test password reset request with invalid email format.
   */
  public function testPasswordResetRequestWithInvalidEmail(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/auth/password/reset/request',
      server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
      ],
      content: json_encode([
        'email' => 'not-an-email',
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
      'Should reject invalid email format',
    );
  }

  /**
   * Test password reset confirm with invalid token.
   */
  public function testPasswordResetConfirmWithInvalidToken(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/auth/password/reset/confirm',
      server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
      ],
      content: json_encode([
        'token' => 'invalid-token-12345',
        'code' => '123456',
        'newPassword' => 'NewSecureP@ss123!',
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Should reject invalid token',
    );
  }

  /**
   * Test password reset confirm with weak password.
   */
  public function testPasswordResetConfirmWithWeakPassword(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/auth/password/reset/confirm',
      server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
      ],
      content: json_encode([
        'token' => 'valid-token-12345',
        'code' => '123456',
        'newPassword' => '123', // Too weak
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
      'Should reject weak password',
    );
  }

  /**
   * Test password reset request without email.
   */
  public function testPasswordResetRequestWithoutEmail(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/auth/password/reset/request',
      server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
      ],
      content: json_encode([]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
      'Should require email field',
    );
  }
}
