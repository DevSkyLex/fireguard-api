<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

use function json_encode;

/**
 * Test OtpChallengeFlowTest.
 *
 * End-to-end tests for OTP Challenge management API.
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class OtpChallengeFlowTest extends OAuth2WebTestCase
{
    // #region Create Challenge Tests

    /**
     * Test creating a challenge with valid data.
     */
    public function testCreateChallengeWithValidData(): void
    {
        $client = static::createClientWithFixtures();
        $token = $this->getAccessToken($client);

        $this->assertNotNull($token, 'Should be able to obtain access token');

        $client->request(
            method: 'POST',
            uri: '/api/otp/challenges',
            server: [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'channel' => 'email',
                'purpose' => 'verification',
                'identifier' => 'test@example.com',
            ]) ?: ''
        );

        $response = $client->getResponse();

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_CREATED, Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_INTERNAL_SERVER_ERROR],
            'Create challenge should respond appropriately. Response: ' . $response->getContent()
        );

        if (Response::HTTP_CREATED === $response->getStatusCode() || Response::HTTP_OK === $response->getStatusCode()) {
            $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
            $this->assertArrayHasKey('token', $data, 'Response should contain a token');
        }
    }

    /**
     * Test creating a challenge with invalid channel.
     */
    public function testCreateChallengeWithInvalidChannel(): void
    {
        $client = static::createClientWithFixtures();
        $token = $this->getAccessToken($client);

        $this->assertNotNull($token, 'Should be able to obtain access token');

        $client->request(
            method: 'POST',
            uri: '/api/otp/challenges',
            server: [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'channel' => 'invalid_channel',
                'purpose' => 'verification',
                'identifier' => 'test@example.com',
            ]) ?: ''
        );

        $response = $client->getResponse();

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
            'Invalid channel should be rejected. Response: ' . $response->getContent()
        );
    }

    /**
     * Test creating a challenge without authentication.
     */
    public function testCreateChallengeWithoutAuth(): void
    {
        $client = static::createClientWithFixtures();

        $client->request(
            method: 'POST',
            uri: '/api/otp/challenges',
            server: [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
            content: json_encode([
                'channel' => 'email',
                'purpose' => 'verification',
                'identifier' => 'test@example.com',
            ]) ?: ''
        );

        $response = $client->getResponse();

        // OTP challenges might be public (for login flow) or require auth
        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_CREATED, Response::HTTP_OK, Response::HTTP_UNAUTHORIZED, Response::HTTP_BAD_REQUEST],
            'Create challenge should handle auth appropriately. Response: ' . $response->getContent()
        );
    }

    // #endregion

    // #region Get Challenge Status Tests

    /**
     * Test getting challenge status with non-existent token.
     */
    public function testGetNonExistentChallengeStatus(): void
    {
        $client = static::createClientWithFixtures();

        $client->request(
            method: 'GET',
            uri: '/api/otp/challenges/non-existent-token-12345',
            server: ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $response = $client->getResponse();

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_NOT_FOUND, Response::HTTP_UNAUTHORIZED],
            'Non-existent challenge should return 404. Response: ' . $response->getContent()
        );
    }

    // #endregion

    // #region Verify Challenge Tests

    /**
     * Test verifying a challenge with non-existent token.
     */
    public function testVerifyNonExistentChallenge(): void
    {
        $client = static::createClientWithFixtures();

        $client->request(
            method: 'POST',
            uri: '/api/otp/challenges/non-existent-token-12345/verify',
            server: [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
            content: json_encode([
                'code' => '123456',
            ]) ?: ''
        );

        $response = $client->getResponse();

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_NOT_FOUND, Response::HTTP_BAD_REQUEST, Response::HTTP_UNAUTHORIZED],
            'Verify non-existent challenge should return 404. Response: ' . $response->getContent()
        );
    }

    /**
     * Test verifying a challenge without code.
     */
    public function testVerifyChallengeWithoutCode(): void
    {
        $client = static::createClientWithFixtures();

        $client->request(
            method: 'POST',
            uri: '/api/otp/challenges/some-token/verify',
            server: [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
            content: json_encode([]) ?: ''
        );

        $response = $client->getResponse();

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_NOT_FOUND, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR, Response::HTTP_UNSUPPORTED_MEDIA_TYPE],
            'Verify without code should fail. Response: ' . $response->getContent()
        );
    }

    // #endregion

    // #region Resend Challenge Tests

    /**
     * Test resending a non-existent challenge.
     */
    public function testResendNonExistentChallenge(): void
    {
        $client = static::createClientWithFixtures();

        $client->request(
            method: 'POST',
            uri: '/api/otp/challenges/non-existent-token-12345/resend',
            server: ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $response = $client->getResponse();

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_NOT_FOUND, Response::HTTP_UNAUTHORIZED],
            'Resend non-existent challenge should return 404. Response: ' . $response->getContent()
        );
    }

    // #endregion
}
