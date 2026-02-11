<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Otp\Infrastructure\Persistence\Doctrine\Record\OtpRecord;
use Symfony\Component\HttpFoundation\Response;

use function is_string;
use function json_encode;
use function password_hash;
use function uniqid;

use const PASSWORD_ARGON2ID;

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

    $testEmail = 'password-reset-' . uniqid() . '@example.com';
    $oldPassword = 'SecurePassword123!';
    $newPassword = 'NewSecureP@ss123!';
    $knownCode = '123456';

    $this->createAndActivateUser($client, $testEmail, $oldPassword);

    // Step 1: Request password reset
    $client->request(
      method: 'POST',
      uri: '/api/auth/password/reset/request',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => $testEmail,
      ]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Password reset request should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertTrue($data['success'] ?? false, 'Request should return success');
    $this->assertArrayHasKey('message', $data);
    $this->assertArrayHasKey('challengeToken', $data);

    $challengeToken = $data['challengeToken'] ?? null;
    $this->assertTrue(is_string($challengeToken) && '' !== $challengeToken, 'Challenge token should be returned for existing user.');

    // Step 2: Force a known OTP code hash in DB for deterministic E2E
    $container = $client->getContainer();
    /** @var \Doctrine\ORM\EntityManagerInterface $em */
    $em = $container->get('doctrine.orm.auth_entity_manager');
    $repo = $em->getRepository(OtpRecord::class);

    /** @var OtpRecord|null $otpRecord */
    $otpRecord = $repo->findOneBy(['challengeToken' => $challengeToken]);
    $this->assertNotNull($otpRecord, 'OTP record should exist for challenge token.');

    $knownHash = password_hash($knownCode, PASSWORD_ARGON2ID);
    $this->assertNotFalse($knownHash, 'Failed to generate deterministic OTP hash.');
    $otpRecord->setCodeHash($knownHash);
    $em->flush();

    // Step 3: Confirm password reset with known OTP code
    $client->request(
      method: 'POST',
      uri: '/api/auth/password/reset/confirm',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'token' => $challengeToken,
        'code' => $knownCode,
        'newPassword' => $newPassword,
      ]) ?: '',
    );

    $confirmResponse = $client->getResponse();
    $this->assertContains(
      $confirmResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Password reset confirmation should succeed. Response: ' . $confirmResponse->getContent(),
    );

    $confirmData = $this->decodeJsonResponse($confirmResponse->getContent() ?: '{}');
    $this->assertTrue($confirmData['success'] ?? false, 'Confirm response should indicate success.');
    $this->assertArrayHasKey('message', $confirmData);

    // Step 4: Old password should no longer authenticate
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => $testEmail,
        'password' => $oldPassword,
      ]) ?: '',
    );

    $oldLoginResponse = $client->getResponse();
    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $oldLoginResponse->getStatusCode(),
      'Old password should be rejected after reset.',
    );

    // Step 5: New password should authenticate
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => $testEmail,
        'password' => $newPassword,
      ]) ?: '',
    );

    $newLoginResponse = $client->getResponse();
    $this->assertContains(
      $newLoginResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED, Response::HTTP_ACCEPTED],
      'New password should authenticate. Response: ' . $newLoginResponse->getContent(),
    );

    $newLoginData = $this->decodeJsonResponse($newLoginResponse->getContent() ?: '{}');
    $hasAccessToken = isset($newLoginData['access_token']) && is_string($newLoginData['access_token']) && '' !== $newLoginData['access_token'];
    $isMfaFlow = true === ($newLoginData['mfa_required'] ?? false);

    $this->assertTrue(
      $hasAccessToken || $isMfaFlow,
      'Login with new password should return access token or MFA challenge.',
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
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => 'nonexistent@example.com',
      ]) ?: '',
    );

    $response = $client->getResponse();

    // Should return 200 to prevent user enumeration
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
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
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
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
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
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
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
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
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
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
