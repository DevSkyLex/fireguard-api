<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\Service;

use Auth\Infrastructure\Adapter\Jwt\JwtTokenAdapter;
use Authorization\Application\Port\Outbound\RoleAssignmentRepositoryPort;
use Authorization\Application\Service\AuthorizationService;
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName, RoleId, RoleName, SubjectType};
use DateTimeImmutable;
use Defuse\Crypto\Crypto;
use InvalidArgumentException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\CachePort;

use function base64_decode;
use function base64_encode;
use function explode;
use function file_exists;
use function file_put_contents;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function openssl_pkey_export;
use function openssl_pkey_get_details;
use function openssl_pkey_new;
use function random_bytes;
use function sleep;
use function sys_get_temp_dir;
use function time;
use function unlink;

use const OPENSSL_KEYTYPE_RSA;

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
   *
   * @var non-empty-string
   */
  private string $privateKeyPath;

  /**
   * Property publicKeyPath.
   *
   * Path to the test public key.
   *
   * @var non-empty-string
   */
  private string $publicKeyPath;

  /**
   * Property encryptionKey.
   *
   * Encryption key used for token encryption.
   *
   * @var non-empty-string
   */
  private string $encryptionKey;
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
    // Generate temporary RSA keys for testing
    $tempDir = sys_get_temp_dir() . '/jwt_test_keys';
    if (!is_dir($tempDir)) {
      mkdir($tempDir, 0700, true);
    }

    $this->privateKeyPath = $tempDir . '/private.key';
    $this->publicKeyPath = $tempDir . '/public.key';

    // Generate a new RSA key pair
    $config = [
      'private_key_bits' => 2048,
      'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];

    $privateKey = openssl_pkey_new($config);
    $this->assertNotFalse($privateKey, 'Failed to generate RSA key pair');

    openssl_pkey_export($privateKey, $privateKeyPem);

    $keyDetails = openssl_pkey_get_details($privateKey);
    $this->assertNotFalse($keyDetails, 'Failed to get key details');

    /** @var string $publicKeyPem */
    $publicKeyPem = $keyDetails['key'];

    file_put_contents($this->privateKeyPath, $privateKeyPem);
    file_put_contents($this->publicKeyPath, $publicKeyPem);

    /** @var non-empty-string $privateKeyPath */
    $privateKeyPath = $this->privateKeyPath;
    /** @var non-empty-string $publicKeyPath */
    $publicKeyPath = $this->publicKeyPath;

    $this->encryptionKey = base64_encode(random_bytes(32));

    $this->service = new JwtTokenAdapter(
      privateKeyPath: $privateKeyPath,
      publicKeyPath: $publicKeyPath,
      encryptionKey: $this->encryptionKey,
      issuer: 'https://test.example.com',
      accessTokenTtl: 3600,
      refreshTokenTtl: 86400,
      authorizationService: null,
      refreshTokenTtlShort: null,
      includeEmailClaim: false,
      includeRbacClaims: false,
    );
  }

  /**
   * Method tearDown.
   *
   * Cleans up the test environment.
   *
   * @return void no return value
   */
  protected function tearDown(): void
  {
    // Clean up temporary key files
    if (isset($this->privateKeyPath) && file_exists($this->privateKeyPath)) {
      unlink($this->privateKeyPath);
    }
    if (isset($this->publicKeyPath) && file_exists($this->publicKeyPath)) {
      unlink($this->publicKeyPath);
    }
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
      scopes: ['READ', 'WRITE'],
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
      scopes: [],
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
      scopes: ['OPENID', 'PROFILE'],
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
      scopes: ['READ'],
    );

    $tokens2 = $this->service->generateTokens(
      userId: 'user-unique',
      email: 'unique@example.com',
      scopes: ['READ'],
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
      refreshTokenTtl: 1,
      authorizationService: null,
      refreshTokenTtlShort: null,
      includeEmailClaim: false,
      includeRbacClaims: false,
    );

    $tokens = $shortTtlService->generateTokens(
      userId: 'user-expired',
      email: 'expired@example.com',
      scopes: ['READ'],
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
      scopes: ['OPENID', 'EMAIL'],
    );

    // Parse the JWT to verify claims
    $parts = explode('.', $tokens['access_token']);
    $this->assertCount(3, $parts, 'JWT should have 3 parts');

    $decoded = base64_decode($parts[1], true);
    $this->assertNotFalse($decoded);
    $payload = json_decode($decoded, true);
    $this->assertIsArray($payload);
    /** @var array<string, mixed> $payload */
    $this->assertArrayHasKey('iss', $payload, 'JWT should have issuer');
    $this->assertArrayHasKey('sub', $payload, 'JWT should have subject');
    $this->assertArrayHasKey('aud', $payload, 'JWT should have audience');
    $this->assertArrayHasKey('exp', $payload, 'JWT should have expiration');
    $this->assertArrayHasKey('iat', $payload, 'JWT should have issued at');
    $this->assertArrayHasKey('jti', $payload, 'JWT should have JWT ID');
    $this->assertArrayHasKey('scopes', $payload, 'JWT should have scopes claim');

    $this->assertEquals('https://test.example.com', $payload['iss']);
    $this->assertEquals('user-claims', $payload['sub']);
    $this->assertEquals(['OPENID', 'EMAIL'], $payload['scopes']);
    $this->assertArrayNotHasKey('email', $payload, 'Email claim should be omitted when includeEmailClaim=false');
    $this->assertArrayNotHasKey('roles', $payload, 'Roles claim should be omitted when includeRbacClaims=false');
    $this->assertArrayNotHasKey('permissions', $payload, 'Permissions claim should be omitted when includeRbacClaims=false');
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
      scopes: ['READ', 'WRITE', 'DELETE'],
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
      refreshTokenTtl: $customRefreshTtl,
      authorizationService: null,
      refreshTokenTtlShort: null,
      includeEmailClaim: false,
      includeRbacClaims: false,
    );

    $this->assertEquals($customAccessTtl, $service->getAccessTokenTtl());
    $this->assertEquals($customRefreshTtl, $service->getRefreshTokenTtl());

    $tokens = $service->generateTokens(
      userId: 'user-ttl',
      email: 'ttl@example.com',
      scopes: ['READ'],
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
      scopes: ['READ'],
    );

    // Parse the JWT to check expiration
    $parts = explode('.', $tokens['access_token']);
    $decoded = base64_decode($parts[1], true);
    $this->assertNotFalse($decoded);
    $payload = json_decode($decoded, true);
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
      scopes: ['READ'],
    );

    $payload = $this->service->decodeRefreshToken($tokens['refresh_token']);
    $this->assertIsArray($payload);
    /** @var array<string, mixed> $payload */
    $expectedExp = time() + $this->service->getRefreshTokenTtl();

    // Allow 5 seconds tolerance
    $this->assertEqualsWithDelta($expectedExp, $payload['expires_at'], 5);
  }

  /**
   * Method testRefreshTokenUsesShortTtlWhenRememberMeFalse.
   *
   * @return void no return value
   */
  #[Test]
  public function testRefreshTokenUsesShortTtlWhenRememberMeFalse(): void
  {
    /** @var non-empty-string $privateKeyPath */
    $privateKeyPath = $this->privateKeyPath;
    /** @var non-empty-string $publicKeyPath */
    $publicKeyPath = $this->publicKeyPath;

    $service = new JwtTokenAdapter(
      privateKeyPath: $privateKeyPath,
      publicKeyPath: $publicKeyPath,
      encryptionKey: base64_encode(random_bytes(32)),
      issuer: 'https://test.example.com',
      accessTokenTtl: 3600,
      refreshTokenTtl: 7200,
      authorizationService: null,
      refreshTokenTtlShort: 600,
      includeEmailClaim: false,
      includeRbacClaims: false,
    );

    $tokens = $service->generateTokens(
      userId: 'user-short',
      email: 'short@example.com',
      scopes: ['READ'],
      rememberMe: false,
    );

    $payload = $service->decodeRefreshToken($tokens['refresh_token']);
    $this->assertIsArray($payload);
    /** @var array<string, mixed> $payload */
    $expectedExp = time() + 600;

    $this->assertArrayHasKey('remember_me', $payload);
    $this->assertFalse($payload['remember_me']);

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
      scopes: ['READ'],
    );

    $parts = explode('.', $tokens['access_token']);
    $decoded = base64_decode($parts[1], true);
    $this->assertNotFalse($decoded);
    $payload = json_decode($decoded, true);
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
      scopes: ['READ'],
    );

    $parts = explode('.', $tokens['access_token']);
    $decoded = base64_decode($parts[1], true);
    $this->assertNotFalse($decoded);
    $payload = json_decode($decoded, true);
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
      scopes: $scopes,
    );

    $parts = explode('.', $tokens['access_token']);
    $decoded = base64_decode($parts[1], true);
    $this->assertNotFalse($decoded);
    $payload = json_decode($decoded, true);
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
      scopes: [],
    );

    $this->assertEquals('Bearer', $tokens['token_type']);
  }

  #[Test]
  public function testGenerateTokensIncludesRolesAndPermissionsWhenAuthorizationServiceProvided(): void
  {
    $role = Role::create(
      id: new RoleId('123e4567-e89b-12d3-a456-426614174000'),
      name: new RoleName('role_admin'),
    );
    $role->addPermission(Permission::create(
      id: new PermissionId('223e4567-e89b-12d3-a456-426614174000'),
      name: new PermissionName('user.read'),
    ));

    $roleAssignmentRepository = $this->createMock(RoleAssignmentRepositoryPort::class);
    $roleAssignmentRepository->method('findRolesForSubject')
      ->with(SubjectType::USER, 'user-roles')
      ->willReturn([$role]);

    $cache = $this->createMock(CachePort::class);
    $cache->method('get')->willReturn(null);

    $authorizationService = new AuthorizationService($roleAssignmentRepository, $cache);

    /** @var non-empty-string $privateKeyPath */
    $privateKeyPath = $this->privateKeyPath;
    /** @var non-empty-string $publicKeyPath */
    $publicKeyPath = $this->publicKeyPath;

    $service = new JwtTokenAdapter(
      privateKeyPath: $privateKeyPath,
      publicKeyPath: $publicKeyPath,
      encryptionKey: $this->encryptionKey,
      issuer: 'https://test.example.com',
      accessTokenTtl: 3600,
      refreshTokenTtl: 86400,
      authorizationService: $authorizationService,
      includeRbacClaims: true,
    );

    $tokens = $service->generateTokens(
      userId: 'user-roles',
      email: 'roles@example.com',
      scopes: ['READ'],
    );

    $parts = explode('.', $tokens['access_token']);
    $decoded = base64_decode($parts[1], true);
    $this->assertNotFalse($decoded);
    $payload = json_decode($decoded, true);
    $this->assertIsArray($payload);
    /** @var array<string, mixed> $payload */
    $this->assertSame(['role_admin'], $payload['roles'] ?? null);
    $this->assertSame(['user.read'], $payload['permissions'] ?? null);
  }

  #[Test]
  public function testAccessTokenIncludesEmailWhenEnabled(): void
  {
    /** @var non-empty-string $privateKeyPath */
    $privateKeyPath = $this->privateKeyPath;
    /** @var non-empty-string $publicKeyPath */
    $publicKeyPath = $this->publicKeyPath;

    $service = new JwtTokenAdapter(
      privateKeyPath: $privateKeyPath,
      publicKeyPath: $publicKeyPath,
      encryptionKey: $this->encryptionKey,
      issuer: 'https://test.example.com',
      accessTokenTtl: 3600,
      refreshTokenTtl: 86400,
      authorizationService: null,
      refreshTokenTtlShort: null,
      includeEmailClaim: true,
      includeRbacClaims: false,
    );

    $tokens = $service->generateTokens(
      userId: 'user-email-claim',
      email: 'claims@example.com',
      scopes: ['OPENID', 'EMAIL'],
    );

    $parts = explode('.', $tokens['access_token']);
    $decoded = base64_decode($parts[1], true);
    $this->assertNotFalse($decoded);
    $payload = json_decode($decoded, true);
    $this->assertIsArray($payload);
    /** @var array<string, mixed> $payload */
    $this->assertSame('claims@example.com', $payload['email'] ?? null);
  }

  #[Test]
  public function testGeneratePreAuthTokenThrowsOnEmptyUserId(): void
  {
    $this->expectException(InvalidArgumentException::class);

    $this->service->generatePreAuthToken(
      userId: '',
      challengeToken: 'challenge',
    );
  }

  #[Test]
  public function testDecodePreAuthTokenReturnsClaims(): void
  {
    $token = $this->service->generatePreAuthToken(
      userId: 'user-preauth',
      challengeToken: 'challenge-token',
      email: 'preauth@example.com',
      scopes: ['SCOPE'],
      ttl: 300,
      rememberMe: true,
    );

    $claims = $this->service->decodePreAuthToken($token);

    $this->assertIsArray($claims);
    $this->assertSame('user-preauth', $claims['sub'] ?? null);
    $this->assertSame('challenge-token', $claims['challenge_token'] ?? null);
    $this->assertTrue($claims['remember_me'] ?? false);
  }

  #[Test]
  public function testDecodePreAuthTokenReturnsNullForEmptyToken(): void
  {
    $this->assertNull($this->service->decodePreAuthToken(''));
  }

  #[Test]
  public function testDecodePreAuthTokenReturnsNullForInvalidSignature(): void
  {
    $token = $this->service->generatePreAuthToken(
      userId: 'user-preauth',
      challengeToken: 'challenge-token',
    );

    $parts = explode('.', $token);
    $this->assertCount(3, $parts);
    $signature = $parts[2];
    $signature[0] = 'A' === $signature[0] ? 'B' : 'A';
    $tampered = $parts[0] . '.' . $parts[1] . '.' . $signature;

    $this->assertNull($this->service->decodePreAuthToken($tampered));
  }

  #[Test]
  public function testDecodePreAuthTokenReturnsNullForExpiredToken(): void
  {
    $expiredToken = $this->createPreAuthToken(expiresAt: new DateTimeImmutable('-1 minute'));

    $this->assertNull($this->service->decodePreAuthToken($expiredToken));
  }

  #[Test]
  public function testDecodePreAuthTokenReturnsNullForInvalidToken(): void
  {
    $this->assertNull($this->service->decodePreAuthToken('not-a-jwt'));
  }

  #[Test]
  public function testDecodeRefreshTokenReturnsNullForNonArrayPayload(): void
  {
    $encrypted = $this->encryptPayload('not-json');

    $this->assertNull($this->service->decodeRefreshToken($encrypted));
  }

  #[Test]
  public function testDecodeRefreshTokenReturnsNullForMissingFields(): void
  {
    $encrypted = $this->encryptPayload([
      'auth_code_id' => 'missing-fields',
    ]);

    $this->assertNull($this->service->decodeRefreshToken($encrypted));
  }

  #[Test]
  public function testDecodeRefreshTokenReturnsNullForInvalidFieldTypes(): void
  {
    $encrypted = $this->encryptPayload([
      'refresh_token_id' => 123,
      'access_token_id' => 'access',
      'user_id' => 'user',
      'scopes' => 'read',
      'expires_at' => 'not-int',
    ]);

    $this->assertNull($this->service->decodeRefreshToken($encrypted));
  }

  #[Test]
  public function testDecodeRefreshTokenCoercesRememberMeString(): void
  {
    $encrypted = $this->encryptPayload([
      'refresh_token_id' => 'refresh-1',
      'access_token_id' => 'access-1',
      'user_id' => 'user-1',
      'scopes' => ['READ'],
      'expires_at' => time() + 3600,
      'remember_me' => 'true',
    ]);

    $payload = $this->service->decodeRefreshToken($encrypted);

    $this->assertIsArray($payload);
    $this->assertTrue($payload['remember_me'] ?? false);
  }

  #[Test]
  public function testDecodeRefreshTokenDropsInvalidRememberMe(): void
  {
    $encrypted = $this->encryptPayload([
      'refresh_token_id' => 'refresh-2',
      'access_token_id' => 'access-2',
      'user_id' => 'user-2',
      'scopes' => ['READ'],
      'expires_at' => time() + 3600,
      'remember_me' => 'maybe',
    ]);

    $payload = $this->service->decodeRefreshToken($encrypted);

    $this->assertIsArray($payload);
    $this->assertArrayNotHasKey('remember_me', $payload);
  }

  private function createPreAuthToken(DateTimeImmutable $expiresAt): string
  {
    $config = Configuration::forAsymmetricSigner(
      signer: new Sha256(),
      signingKey: InMemory::file($this->privateKeyPath),
      verificationKey: InMemory::file($this->publicKeyPath),
    );

    $now = new DateTimeImmutable('-2 hours');

    $token = $config->builder()
      ->issuedBy('https://test.example.com')
      ->permittedFor('https://test.example.com')
      ->identifiedBy('preauth-id')
      ->relatedTo('user-preauth')
      ->issuedAt($now)
      ->expiresAt($expiresAt)
      ->withClaim('scope', 'pre_auth')
      ->getToken($config->signer(), $config->signingKey());

    return $token->toString();
  }

  /**
   * @param array<string, mixed>|string $payload
   */
  private function encryptPayload(array|string $payload): string
  {
    if (is_array($payload)) {
      $json = json_encode($payload);
      $this->assertIsString($json);
      $stringData = $json;
    } else {
      $stringData = $payload;
    }

    return Crypto::encryptWithPassword($stringData, $this->encryptionKey);
  }
  // #endregion
}
