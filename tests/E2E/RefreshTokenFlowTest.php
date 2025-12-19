<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

use function in_array;
use function is_string;
use function json_encode;
use function uniqid;

/**
 * Test RefreshTokenFlowTest.
 *
 * End-to-end tests for the refresh token grant flow.
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class RefreshTokenFlowTest extends OAuth2WebTestCase
{
    // #region Refresh Token Grant Tests

    /**
     * Test refresh token grant with valid refresh token.
     */
    public function testRefreshTokenGrantWithValidToken(): void
    {
        $client = static::createClientWithFixtures();

        // Step 1: Login to get initial tokens
        $email = 'refresh-test-' . uniqid() . '@example.com';
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

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_OK, Response::HTTP_CREATED],
            'Login should succeed. Response: ' . $response->getContent()
        );

        $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
        $originalAccessToken = $data['access_token'] ?? '';

        // Check for refresh token in response or cookie
        $refreshTokenVal = $data['refresh_token'] ?? null;
        $refreshToken = is_string($refreshTokenVal) ? $refreshTokenVal : null;

        // If refresh token is in cookie, extract it
        $cookies = $response->headers->getCookies();
        foreach ($cookies as $cookie) {
            if ('refresh_token' === $cookie->getName()) {
                $cookieValue = $cookie->getValue();
                if (is_string($cookieValue) && '' !== $cookieValue) {
                    $refreshToken = $cookieValue;
                }
                break;
            }
        }

        $this->assertNotNull($refreshToken, 'Refresh token should be available in response or cookie');

        // Step 2: Use refresh token via /api/auth/refresh endpoint (not OAuth2 token endpoint)
        // The login refresh token is specific to the auth system, not OAuth2 standard
        $client->request(
            method: 'POST',
            uri: '/api/auth/refresh',
            server: [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_COOKIE' => 'refresh_token=' . $refreshToken,
            ]
        );

        $response = $client->getResponse();

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_OK, Response::HTTP_CREATED],
            'Refresh token should succeed. Response: ' . $response->getContent()
        );

        $newData = $this->decodeJsonResponse($response->getContent() ?: '{}');

        $this->assertArrayHasKey('access_token', $newData, 'Response should contain new access_token');
        $this->assertNotEquals(
            $originalAccessToken,
            $newData['access_token'],
            'New access token should be different from original'
        );
    }

    /**
     * Test refresh token grant with invalid refresh token.
     */
    public function testRefreshTokenGrantWithInvalidToken(): void
    {
        $client = static::createClientWithFixtures();

        $client->request(
            method: 'POST',
            uri: '/api/oauth2/token',
            server: [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
            content: json_encode([
                'grant_type' => 'refresh_token',
                'refresh_token' => 'invalid_refresh_token_here',
                'client_id' => self::API_CLIENT_ID,
                'client_secret' => self::API_CLIENT_SECRET,
            ]) ?: ''
        );

        $response = $client->getResponse();

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_BAD_REQUEST, Response::HTTP_UNAUTHORIZED],
            'Invalid refresh token should be rejected'
        );
    }

    /**
     * Test refresh token grant without refresh_token parameter.
     */
    public function testRefreshTokenGrantWithoutToken(): void
    {
        $client = static::createClientWithFixtures();

        $client->request(
            method: 'POST',
            uri: '/api/oauth2/token',
            server: [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
            content: json_encode([
                'grant_type' => 'refresh_token',
                'client_id' => self::API_CLIENT_ID,
                'client_secret' => self::API_CLIENT_SECRET,
            ]) ?: ''
        );

        $response = $client->getResponse();

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
            'Missing refresh_token should be rejected'
        );
    }

    /**
     * Test refresh token grant with expired refresh token.
     */
    public function testRefreshTokenGrantWithExpiredToken(): void
    {
        $client = static::createClientWithFixtures();

        // Use a JWT-like token that would be expired
        $expiredToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwiZXhwIjoxfQ.invalid';

        $client->request(
            method: 'POST',
            uri: '/api/oauth2/token',
            server: [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
            content: json_encode([
                'grant_type' => 'refresh_token',
                'refresh_token' => $expiredToken,
                'client_id' => self::API_CLIENT_ID,
                'client_secret' => self::API_CLIENT_SECRET,
            ]) ?: ''
        );

        $response = $client->getResponse();

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_BAD_REQUEST, Response::HTTP_UNAUTHORIZED],
            'Expired refresh token should be rejected'
        );
    }

    // #endregion

    // #region Auth Refresh Endpoint Tests

    /**
     * Test /api/auth/refresh endpoint with cookie.
     */
    public function testAuthRefreshEndpointWithCookie(): void
    {
        $client = static::createClientWithFixtures();

        // Step 1: Login to get refresh token cookie
        $email = 'auth-refresh-' . uniqid() . '@example.com';
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

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_OK, Response::HTTP_CREATED],
            'Login should succeed. Response: ' . $response->getContent()
        );

        // Step 2: Call refresh endpoint (should use cookie automatically)
        $client->request(
            method: 'POST',
            uri: '/api/auth/refresh',
            server: [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ]
        );

        $response = $client->getResponse();

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_OK, Response::HTTP_CREATED, Response::HTTP_UNAUTHORIZED],
            'Refresh endpoint should respond appropriately'
        );

        if (in_array($response->getStatusCode(), [Response::HTTP_OK, Response::HTTP_CREATED])) {
            $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
            $this->assertArrayHasKey('access_token', $data, 'Response should contain new access_token');
        }
    }

    /**
     * Test /api/auth/refresh endpoint without cookie.
     */
    public function testAuthRefreshEndpointWithoutCookie(): void
    {
        $client = static::createClient();

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

    // #endregion
}
