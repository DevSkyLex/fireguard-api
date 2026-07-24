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
 * Test RegistrationFlowTest.
 *
 * End-to-end tests for the public self-service registration flow.
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class RegistrationFlowTest extends OAuth2WebTestCase
{
  /**
   * Test the complete registration flow.
   *
   * This test verifies:
   * 1. A prospect can register and receives a verification challenge.
   * 2. The account cannot log in before email verification.
   * 3. Verifying the OTP code activates the account and returns tokens (auto-login).
   * 4. The account can then log in with its credentials.
   */
  public function testCompleteRegistrationFlow(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'register-' . uniqid() . '@example.com';
    $password = 'SecureP@ss123!';
    $knownCode = '123456';

    // Step 1: Register
    $client->request(
      method: 'POST',
      uri: '/api/auth/register',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'email' => $email,
        'password' => $password,
      ]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Registration should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertTrue($data['success'] ?? false, 'Registration should return success.');
    $this->assertArrayHasKey('maskedRecipient', $data);

    $challengeToken = $data['challengeToken'] ?? null;
    $this->assertTrue(is_string($challengeToken) && '' !== $challengeToken, 'Challenge token should be returned.');

    // Step 2: A pending-verification account cannot log in yet.
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

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $client->getResponse()->getStatusCode(),
      'Unverified account must not be able to log in.',
    );

    // Step 3: Force a known OTP code hash in DB for deterministic E2E.
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

    // Step 4: Verify email with the known code -> auto-login tokens returned.
    $client->request(
      method: 'POST',
      uri: '/api/auth/register/verify',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'token' => $challengeToken,
        'code' => $knownCode,
      ]) ?: '',
    );

    $verifyResponse = $client->getResponse();
    $this->assertContains(
      $verifyResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Email verification should succeed. Response: ' . $verifyResponse->getContent(),
    );

    $verifyData = $this->decodeJsonResponse($verifyResponse->getContent() ?: '{}');
    $accessToken = $verifyData['access_token'] ?? null;
    $this->assertTrue(
      is_string($accessToken) && '' !== $accessToken,
      'Verification should auto-login and return an access token.',
    );

    // Step 5: The account can now log in with its credentials.
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
      $loginResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED, Response::HTTP_ACCEPTED],
      'Verified account should be able to log in. Response: ' . $loginResponse->getContent(),
    );

    $loginData = $this->decodeJsonResponse($loginResponse->getContent() ?: '{}');
    $hasAccessToken = isset($loginData['access_token']) && is_string($loginData['access_token']) && '' !== $loginData['access_token'];
    $isMfaFlow = true === ($loginData['mfa_required'] ?? false);
    $this->assertTrue($hasAccessToken || $isMfaFlow, 'Login should return access token or MFA challenge.');
  }

  /**
   * Test registration with an already-registered email returns 409.
   */
  public function testRegisterWithDuplicateEmailIsRejected(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'dupe-' . uniqid() . '@example.com';
    $password = 'SecureP@ss123!';

    $this->createAndActivateUser($client, $email, $password);

    $client->request(
      method: 'POST',
      uri: '/api/auth/register',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'email' => $email,
        'password' => $password,
      ]) ?: '',
    );

    $this->assertEquals(
      Response::HTTP_CONFLICT,
      $client->getResponse()->getStatusCode(),
      'Registering an existing email should return 409.',
    );
  }

  /**
   * Test registration with an invalid email format is rejected.
   */
  public function testRegisterWithInvalidEmailIsRejected(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/auth/register',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'email' => 'not-an-email',
        'password' => 'SecureP@ss123!',
      ]) ?: '',
    );

    $this->assertContains(
      $client->getResponse()->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
      'Should reject invalid email format.',
    );
  }

  /**
   * Test registration with a weak password is rejected.
   */
  public function testRegisterWithWeakPasswordIsRejected(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/auth/register',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'email' => 'weakpass-' . uniqid() . '@example.com',
        'password' => '123',
      ]) ?: '',
    );

    $this->assertContains(
      $client->getResponse()->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
      'Should reject a weak password.',
    );
  }

  /**
   * Test verifying with an invalid token is rejected.
   */
  public function testConfirmRegistrationWithInvalidTokenIsRejected(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/auth/register/verify',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'token' => 'invalid-token-12345',
        'code' => '123456',
      ]) ?: '',
    );

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $client->getResponse()->getStatusCode(),
      'Should reject an invalid verification token.',
    );
  }

  /**
   * Test that resending immediately after registration is rate limited by the
   * resend cooldown.
   */
  public function testResendDuringCooldownIsRejected(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'resend-' . uniqid() . '@example.com';

    $client->request(
      method: 'POST',
      uri: '/api/auth/register',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'email' => $email,
        'password' => 'SecureP@ss123!',
      ]) ?: '',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $challengeToken = $data['challengeToken'] ?? null;
    $this->assertTrue(is_string($challengeToken) && '' !== $challengeToken, 'Challenge token should be returned.');

    // Resending within the cooldown window must be rejected.
    $client->request(
      method: 'POST',
      uri: '/api/auth/register/resend',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'token' => $challengeToken,
      ]) ?: '',
    );

    $this->assertEquals(
      Response::HTTP_TOO_MANY_REQUESTS,
      $client->getResponse()->getStatusCode(),
      'Resending during the cooldown should return 429.',
    );
  }
}
