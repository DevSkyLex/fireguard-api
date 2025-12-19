<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\Service;

use Auth\Infrastructure\Adapter\Jwt\JwtTokenAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_decode;
use function base64_encode;
use function dirname;
use function explode;
use function file_exists;
use function json_decode;
use function random_bytes;
use function sleep;
use function time;

/**
 * Class JwtTokenServiceTest.
 *
 * Unit tests for the JwtTokenService.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Infrastructure\Adapter\Jwt\JwtTokenAdapter
 */
#[CoversClass(className: JwtTokenAdapter::class)]
final class JwtTokenServiceTest extends TestCase
{
    // #region Properties
    /**
     * Property service.
     *
     * Instance of the JwtTokenService class.
     */
    private JwtTokenAdapter $service;

    /**
     * Property privateKeyPath.
     *
     * Path to the test private key.
     */
    private string $privateKeyPath;

    /**
     * Property publicKeyPath.
     *
     * Path to the test public key.
     */
    private string $publicKeyPath;
    // #endregion

    // #region Methods
    /**
     * Method setUp.
     *
     * Sets up the test environment.
     *
     * @return void no return value
     */
    protected function setUp(): void
    {
        $this->privateKeyPath = dirname(__DIR__, 5) . '/config/jwt/private.key';
        $this->publicKeyPath = dirname(__DIR__, 5) . '/config/jwt/public.key';

        // Skip if keys don't exist
        if (!file_exists($this->privateKeyPath) || !file_exists($this->publicKeyPath)) {
            $this->markTestSkipped('JWT keys not found in config/jwt/. Generate keys first.');
        }

        $this->service = new JwtTokenAdapter(
            privateKeyPath: $this->privateKeyPath,
            publicKeyPath: $this->publicKeyPath,
            encryptionKey: base64_encode(random_bytes(32)),
            issuer: 'https://test.example.com',
            accessTokenTtl: 3600,
            refreshTokenTtl: 86400
        );
    }

    /**
     * Method testGenerateTokensReturnsValidStructure.
     *
     * Tests that generateTokens returns a valid token structure.
     *
     * @return void no return value
     */
    #[Test]
    public function testGenerateTokensReturnsValidStructure(): void
    {
        $tokens = $this->service->generateTokens(
            userId: 'user-123',
            email: 'test@example.com',
            scopes: ['READ', 'WRITE']
        );

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertArrayHasKey('token_type', $tokens);
        $this->assertArrayHasKey('expires_in', $tokens);

        $this->assertEquals('Bearer', $tokens['token_type']);
        $this->assertEquals(3600, $tokens['expires_in']);
        $this->assertNotEmpty($tokens['access_token']);
        $this->assertNotEmpty($tokens['refresh_token']);
    }

    /**
     * Method testGenerateTokensWithEmptyScopes.
     *
     * Tests that generateTokens works with empty scopes.
     *
     * @return void no return value
     */
    #[Test]
    public function testGenerateTokensWithEmptyScopes(): void
    {
        $tokens = $this->service->generateTokens(
            userId: 'user-456',
            email: 'empty-scopes@example.com',
            scopes: []
        );

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
    }

    /**
     * Method testDecodeRefreshTokenReturnsValidPayload.
     *
     * Tests that decodeRefreshToken returns a valid payload.
     *
     * @return void no return value
     */
    #[Test]
    public function testDecodeRefreshTokenReturnsValidPayload(): void
    {
        $tokens = $this->service->generateTokens(
            userId: 'user-789',
            email: 'decode@example.com',
            scopes: ['OPENID', 'PROFILE']
        );

        $payload = $this->service->decodeRefreshToken($tokens['refresh_token']);
        $this->assertIsArray($payload);
        /** @var array<string, mixed> $payload */
        $this->assertArrayHasKey('refresh_token_id', $payload);
        $this->assertArrayHasKey('access_token_id', $payload);
        $this->assertArrayHasKey('user_id', $payload);
        $this->assertArrayHasKey('scopes', $payload);
        $this->assertArrayHasKey('expires_at', $payload);

        $this->assertEquals('user-789', $payload['user_id']);
        $this->assertEquals(['OPENID', 'PROFILE'], $payload['scopes']);
    }

    /**
     * Method testDecodeRefreshTokenReturnsNullForInvalidToken.
     *
     * Tests that decodeRefreshToken returns null for invalid tokens.
     *
     * @return void no return value
     */
    #[Test]
    public function testDecodeRefreshTokenReturnsNullForInvalidToken(): void
    {
        $payload = $this->service->decodeRefreshToken('invalid_token_string');

        $this->assertNull($payload);
    }

    /**
     * Method testDecodeRefreshTokenReturnsNullForEmptyToken.
     *
     * Tests that decodeRefreshToken returns null for empty tokens.
     *
     * @return void no return value
     */
    #[Test]
    public function testDecodeRefreshTokenReturnsNullForEmptyToken(): void
    {
        $payload = $this->service->decodeRefreshToken('');

        $this->assertNull($payload);
    }

    /**
     * Method testGetAccessTokenTtl.
     *
     * Tests that getAccessTokenTtl returns the configured value.
     *
     * @return void no return value
     */
    #[Test]
    public function testGetAccessTokenTtl(): void
    {
        $this->assertEquals(3600, $this->service->getAccessTokenTtl());
    }

    /**
     * Method testGetRefreshTokenTtl.
     *
     * Tests that getRefreshTokenTtl returns the configured value.
     *
     * @return void no return value
     */
    #[Test]
    public function testGetRefreshTokenTtl(): void
    {
        $this->assertEquals(86400, $this->service->getRefreshTokenTtl());
    }

    /**
     * Method testGeneratedTokensAreUnique.
     *
     * Tests that each call generates unique tokens.
     *
     * @return void no return value
     */
    #[Test]
    public function testGeneratedTokensAreUnique(): void
    {
        $tokens1 = $this->service->generateTokens(
            userId: 'user-unique',
            email: 'unique@example.com',
            scopes: ['READ']
        );

        $tokens2 = $this->service->generateTokens(
            userId: 'user-unique',
            email: 'unique@example.com',
            scopes: ['READ']
        );

        $this->assertNotEquals($tokens1['access_token'], $tokens2['access_token']);
        $this->assertNotEquals($tokens1['refresh_token'], $tokens2['refresh_token']);
    }

    /**
     * Method testDecodeRefreshTokenReturnsNullForExpiredToken.
     *
     * Tests that decodeRefreshToken returns null for expired tokens.
     *
     * @return void no return value
     */
    #[Test]
    public function testDecodeRefreshTokenReturnsNullForExpiredToken(): void
    {
        // Create a service with very short TTL (1 second)
        /** @var non-empty-string $privateKeyPath */
        $privateKeyPath = $this->privateKeyPath;
        /** @var non-empty-string $publicKeyPath */
        $publicKeyPath = $this->publicKeyPath;

        $shortTtlService = new JwtTokenAdapter(
            privateKeyPath: $privateKeyPath,
            publicKeyPath: $publicKeyPath,
            encryptionKey: base64_encode(random_bytes(32)),
            issuer: 'https://test.example.com',
            accessTokenTtl: 1,
            refreshTokenTtl: 1
        );

        $tokens = $shortTtlService->generateTokens(
            userId: 'user-expired',
            email: 'expired@example.com',
            scopes: ['READ']
        );

        // Wait for token to expire
        sleep(2);

        $payload = $shortTtlService->decodeRefreshToken($tokens['refresh_token']);

        $this->assertNull($payload, 'Expired token should return null');
    }

    /**
     * Method testDecodeRefreshTokenReturnsNullForMalformedJson.
     *
     * Tests that decodeRefreshToken returns null for malformed JSON.
     *
     * @return void no return value
     */
    #[Test]
    public function testDecodeRefreshTokenReturnsNullForMalformedJson(): void
    {
        // A base64 encoded string that is not valid JSON
        $malformedToken = base64_encode('not-valid-json');

        $payload = $this->service->decodeRefreshToken($malformedToken);

        $this->assertNull($payload);
    }

    /**
     * Method testAccessTokenContainsExpectedClaims.
     *
     * Tests that access token JWT contains expected claims.
     *
     * @return void no return value
     */
    #[Test]
    public function testAccessTokenContainsExpectedClaims(): void
    {
        $tokens = $this->service->generateTokens(
            userId: 'user-claims',
            email: 'claims@example.com',
            scopes: ['OPENID', 'EMAIL']
        );

        // Parse the JWT to verify claims
        $parts = explode('.', $tokens['access_token']);
        $this->assertCount(3, $parts, 'JWT should have 3 parts');

        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertIsArray($payload);
        /** @var array<string, mixed> $payload */
        $this->assertArrayHasKey('iss', $payload, 'JWT should have issuer');
        $this->assertArrayHasKey('sub', $payload, 'JWT should have subject');
        $this->assertArrayHasKey('aud', $payload, 'JWT should have audience');
        $this->assertArrayHasKey('exp', $payload, 'JWT should have expiration');
        $this->assertArrayHasKey('iat', $payload, 'JWT should have issued at');
        $this->assertArrayHasKey('jti', $payload, 'JWT should have JWT ID');
        $this->assertArrayHasKey('email', $payload, 'JWT should have email claim');
        $this->assertArrayHasKey('scopes', $payload, 'JWT should have scopes claim');

        $this->assertEquals('https://test.example.com', $payload['iss']);
        $this->assertEquals('user-claims', $payload['sub']);
        $this->assertEquals('claims@example.com', $payload['email']);
        $this->assertEquals(['OPENID', 'EMAIL'], $payload['scopes']);
    }

    /**
     * Method testRefreshTokenPayloadContainsAllRequiredFields.
     *
     * Tests that refresh token payload contains all required fields with correct types.
     *
     * @return void no return value
     */
    #[Test]
    public function testRefreshTokenPayloadContainsAllRequiredFields(): void
    {
        $tokens = $this->service->generateTokens(
            userId: 'user-fields',
            email: 'fields@example.com',
            scopes: ['READ', 'WRITE', 'DELETE']
        );

        $payload = $this->service->decodeRefreshToken($tokens['refresh_token']);
        $this->assertIsArray($payload);
        /** @var array<string, mixed> $payload */

        // Verify values are not empty
        $this->assertNotEmpty($payload['refresh_token_id'], 'refresh_token_id should not be empty');
        $this->assertNotEmpty($payload['access_token_id'], 'access_token_id should not be empty');
        $this->assertNotEmpty($payload['user_id'], 'user_id should not be empty');
        $this->assertNotEmpty($payload['scopes'], 'scopes should not be empty');
        $this->assertGreaterThan(0, $payload['expires_at'], 'expires_at should be positive');

        // Verify values
        $this->assertNotEmpty($payload['refresh_token_id']);
        $this->assertNotEmpty($payload['access_token_id']);
        $this->assertEquals('user-fields', $payload['user_id']);
        $this->assertEquals(['READ', 'WRITE', 'DELETE'], $payload['scopes']);
        $this->assertGreaterThan(time(), $payload['expires_at']);
    }

    /**
     * Method testCustomTtlValues.
     *
     * Tests that custom TTL values are respected.
     *
     * @return void no return value
     */
    #[Test]
    public function testCustomTtlValues(): void
    {
        /** @var non-empty-string $privateKeyPath */
        $privateKeyPath = $this->privateKeyPath;
        /** @var non-empty-string $publicKeyPath */
        $publicKeyPath = $this->publicKeyPath;

        $customAccessTtl = 1800;  // 30 minutes
        $customRefreshTtl = 43200; // 12 hours

        $service = new JwtTokenAdapter(
            privateKeyPath: $privateKeyPath,
            publicKeyPath: $publicKeyPath,
            encryptionKey: base64_encode(random_bytes(32)),
            issuer: 'https://test.example.com',
            accessTokenTtl: $customAccessTtl,
            refreshTokenTtl: $customRefreshTtl
        );

        $this->assertEquals($customAccessTtl, $service->getAccessTokenTtl());
        $this->assertEquals($customRefreshTtl, $service->getRefreshTokenTtl());

        $tokens = $service->generateTokens(
            userId: 'user-ttl',
            email: 'ttl@example.com',
            scopes: ['READ']
        );

        $this->assertEquals($customAccessTtl, $tokens['expires_in']);
    }

    /**
     * Method testAccessTokenExpirationMatchesTtl.
     *
     * Tests that access token expiration matches configured TTL.
     *
     * @return void no return value
     */
    #[Test]
    public function testAccessTokenExpirationMatchesTtl(): void
    {
        $tokens = $this->service->generateTokens(
            userId: 'user-exp',
            email: 'exp@example.com',
            scopes: ['READ']
        );

        // Parse the JWT to check expiration
        $parts = explode('.', $tokens['access_token']);
        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertIsArray($payload);
        /** @var array<string, mixed> $payload */
        $expectedExp = time() + $this->service->getAccessTokenTtl();

        // Allow 5 seconds tolerance
        $this->assertEqualsWithDelta($expectedExp, $payload['exp'], 5);
    }

    /**
     * Method testRefreshTokenExpirationMatchesTtl.
     *
     * Tests that refresh token expiration matches configured TTL.
     *
     * @return void no return value
     */
    #[Test]
    public function testRefreshTokenExpirationMatchesTtl(): void
    {
        $tokens = $this->service->generateTokens(
            userId: 'user-refresh-exp',
            email: 'refresh-exp@example.com',
            scopes: ['READ']
        );

        $payload = $this->service->decodeRefreshToken($tokens['refresh_token']);
        $this->assertIsArray($payload);
        /** @var array<string, mixed> $payload */
        $expectedExp = time() + $this->service->getRefreshTokenTtl();

        // Allow 5 seconds tolerance
        $this->assertEqualsWithDelta($expectedExp, $payload['expires_at'], 5);
    }

    /**
     * Method testIssuerClaimMatchesConfiguration.
     *
     * Tests that issuer claim matches the configured issuer.
     *
     * @return void no return value
     */
    #[Test]
    public function testIssuerClaimMatchesConfiguration(): void
    {
        $tokens = $this->service->generateTokens(
            userId: 'user-issuer',
            email: 'issuer@example.com',
            scopes: ['READ']
        );

        $parts = explode('.', $tokens['access_token']);
        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertIsArray($payload);
        /** @var array<string, mixed> $payload */
        $this->assertEquals('https://test.example.com', $payload['iss']);
    }

    /**
     * Method testSubjectClaimMatchesUserId.
     *
     * Tests that subject claim matches the user ID.
     *
     * @return void no return value
     */
    #[Test]
    public function testSubjectClaimMatchesUserId(): void
    {
        $userId = 'user-subject-test-123';

        $tokens = $this->service->generateTokens(
            userId: $userId,
            email: 'subject@example.com',
            scopes: ['READ']
        );

        $parts = explode('.', $tokens['access_token']);
        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertIsArray($payload);
        /** @var array<string, mixed> $payload */
        $this->assertEquals($userId, $payload['sub']);
    }

    /**
     * Method testScopesArePreservedInToken.
     *
     * Tests that scopes are correctly preserved in the token.
     *
     * @return void no return value
     */
    #[Test]
    public function testScopesArePreservedInToken(): void
    {
        $scopes = ['OPENID', 'PROFILE', 'EMAIL', 'READ', 'WRITE'];

        $tokens = $this->service->generateTokens(
            userId: 'user-scopes',
            email: 'scopes@example.com',
            scopes: $scopes
        );

        $parts = explode('.', $tokens['access_token']);
        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertIsArray($payload);
        /** @var array<string, mixed> $payload */
        $this->assertEquals($scopes, $payload['scopes']);

        // Also verify in refresh token
        $refreshPayload = $this->service->decodeRefreshToken($tokens['refresh_token']);
        $this->assertNotNull($refreshPayload);
        $this->assertEquals($scopes, $refreshPayload['scopes']);
    }

    /**
     * Method testTokenTypeIsBearerInResponse.
     *
     * Tests that token type is always Bearer.
     *
     * @return void no return value
     */
    #[Test]
    public function testTokenTypeIsBearerInResponse(): void
    {
        $tokens = $this->service->generateTokens(
            userId: 'user-type',
            email: 'type@example.com',
            scopes: []
        );

        $this->assertEquals('Bearer', $tokens['token_type']);
    }
    // #endregion
}
