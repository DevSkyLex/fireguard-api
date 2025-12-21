<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

use function file_put_contents;
use function is_string;
use function json_encode;
use function password_hash;
use function putenv;
use function uniqid;

/**
 * Test MfaFlowTest.
 *
 * End-to-end tests for the MFA flow.
 *
 * @category E2E Tests
 */
class MfaFlowTest extends OAuth2WebTestCase
{
  /**
   * Test MFA login flow - Single Kernel Boot.
   */
  public function testMfaLoginFlow(): void
  {
    // Set ENV vars before any client/kernel creation
    putenv('MFA_ENABLED=true');
    $_ENV['MFA_ENABLED'] = 'true';
    $_SERVER['MFA_ENABLED'] = 'true';

    $client = static::createClientWithFixtures();

    // 1. Create User
    $email = 'mfa-test-' . uniqid() . '@example.com';
    $password = 'TestPassword123!';
    $this->createAndActivateUser($client, $email, $password);

    // 2. Login (Expect 202 or 200 with MFA required)
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

    if (500 === $response->getStatusCode()) {
      echo "\n=== 500 ERROR RESPONSE ===\n";
      echo $response->getContent();
      echo "\n==========================\n";
    }

    // Output logic flow trace (if any appeared in stderr)
    // ... handled by phpunit output capture

    $this->assertContains($response->getStatusCode(), [200, 201, 202]);

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');

    $this->assertArrayHasKey('mfa_required', $data);
    $this->assertTrue($data['mfa_required'], 'MFA should be required');
    $this->assertArrayHasKey('mfa_token', $data);
    $this->assertArrayHasKey('challenge_token', $data);

    $preAuthToken = $data['mfa_token'];
    $challengeToken = $data['challenge_token'];

    // 3. Force Known Code (Bypass Email)
    $container = $client->getContainer();
    /** @var \Doctrine\ORM\EntityManagerInterface $em */
    $em = $container->get('doctrine.orm.entity_manager');
    $repo = $em->getRepository(\Otp\Infrastructure\Persistence\Doctrine\Record\OtpRecord::class);

    /** @var \Otp\Infrastructure\Persistence\Doctrine\Record\OtpRecord|null $otpRecord */
    $otpRecord = $repo->findOneBy(['challengeToken' => $challengeToken]);

    $this->assertNotNull($otpRecord, 'OTP Record not found for challenge token');

    $knownCode = '123456';
    $knownHash = password_hash($knownCode, PASSWORD_ARGON2ID);

    $otpRecord->setCodeHash($knownHash);
    $em->flush();

    // 4. Verify MFA
    $client->request(
      method: 'POST',
      uri: '/api/auth/mfa/verify',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'preAuthToken' => $preAuthToken,
        'code' => $knownCode,
      ]) ?: '',
    );

    $verifyResponse = $client->getResponse();

    if (200 !== $verifyResponse->getStatusCode() && 201 !== $verifyResponse->getStatusCode()) {
      file_put_contents('g:\Projets\fireguard\fireguard-auth\mfa_error.json', $verifyResponse->getContent());
    }

    if (200 !== $verifyResponse->getStatusCode() && 201 !== $verifyResponse->getStatusCode()) {
      file_put_contents('g:\Projets\fireguard\fireguard-auth\mfa_error.json', $verifyResponse->getContent());
    }

    if (500 === $verifyResponse->getStatusCode()) {
      echo "\n=== 500 ERROR RESPONSE VERIFY ===\n";
      echo $verifyResponse->getContent();
      echo "\n=================================\n";
    }

    $this->assertContains(
      $verifyResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'MFA verify should succeed. Response: ' . $verifyResponse->getContent(),
    );

    $verifyData = $this->decodeJsonResponse($verifyResponse->getContent() ?: '{}');

    $this->assertArrayHasKey('access_token', $verifyData);
    $this->assertArrayHasKey('token_type', $verifyData);
    $this->assertEquals('Bearer', $verifyData['token_type']);

    // Ensure tokens work (logout)
    $tokenValue = $verifyData['access_token'] ?? '';
    $accessToken = is_string($tokenValue) ? $tokenValue : '';

    $client->request(
      method: 'POST',
      uri: '/api/auth/logout',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken,
      ],
    );

    $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
  }

  /**
   * Tear down after each test.
   */
  protected function tearDown(): void
  {
    putenv('MFA_ENABLED=false');
    unset($_ENV['MFA_ENABLED']);
    unset($_SERVER['MFA_ENABLED']);

    parent::tearDown();
  }
}
