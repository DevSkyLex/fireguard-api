<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\Federation;

use Auth\Application\Contract\Federation\{FederatedFlow, FederatedLoginContext, ProviderId};
use Auth\Application\Port\Inbound\InteractiveSessionIssuerPort;
use Auth\Application\Port\Outbound\AuthenticationPolicyPort;
use Auth\Application\Port\Outbound\Federation\{FederatedFlowRepositoryPort, FederatedIdentityRepositoryPort};
use Auth\Application\Service\Federation\FederatedAuthenticationService;
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Auth\Infrastructure\Adapter\Federation\{ConfiguredFederatedProviderRegistryAdapter, GenericOidcProviderAdapter, LeagueFederatedProviderAdapter};
use DateTimeImmutable;
use InvalidArgumentException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use User\Application\Port\Inbound\FederatedUserPort;

use function array_key_exists;
use function array_map;
use function base64_encode;
use function hash;
use function json_encode;
use function openssl_pkey_export;
use function openssl_pkey_get_details;
use function openssl_pkey_new;
use function parse_str;
use function parse_url;
use function rtrim;
use function strtr;

use const JSON_THROW_ON_ERROR;
use const OPENSSL_KEYTYPE_RSA;
use const PHP_URL_QUERY;

/**
 * Test GenericOidcProviderAdapter
 *
 * Uses ephemeral RSA signing keys and mocked HTTPS provider responses.
 *
 * @category Test
 */
#[CoversClass(GenericOidcProviderAdapter::class)]
#[CoversClass(ConfiguredFederatedProviderRegistryAdapter::class)]
#[CoversClass(FederatedAuthenticationService::class)]
final class GenericOidcProviderAdapterTest extends TestCase
{
  #[Test]
  public function itStartsAnOpenProviderWithPkceAndAHashedNonce(): void
  {
    $adapter = new GenericOidcProviderAdapter(new MockHttpClient(), $this->providers());
    $authorization = $adapter->start(ProviderId::from('acme'), 'https://app.example/auth/federated/acme/callback', 'expected-state');
    $query = parse_url($authorization->authorizationUrl, PHP_URL_QUERY);
    self::assertIsString($query);
    parse_str($query, $parameters);

    self::assertSame('expected-state', $parameters['state']);
    self::assertSame('S256', $parameters['code_challenge_method']);
    self::assertSame($this->base64Url(hash('sha256', $authorization->codeVerifier, true)), $parameters['code_challenge']);
    self::assertIsString($parameters['nonce']);
    self::assertSame(hash('sha256', $parameters['nonce']), $authorization->nonceHash);
    self::assertSame('https://app.example/auth/federated/acme/callback', $parameters['redirect_uri']);
    self::assertSame('acme', $adapter->descriptors()[0]->id->value);
    self::assertSame('Acme identity', $adapter->descriptors()[0]->label);
  }

  #[Test]
  public function itVerifiesAConfiguredGenericIssuerBeforeReturningAProfile(): void
  {
    [$privateKey, $jwk] = $this->keyPair();
    $token = $this->token($privateKey);
    $calls = [];
    $http = new MockHttpClient(function (string $method, string $url, array $options) use ($token, $jwk, &$calls): MockResponse {
      $calls[] = [$method, $url];
      self::assertSame(0, $options['max_redirects']);
      if ('POST' === $method) {
        self::assertIsString($options['body']);
        parse_str($options['body'], $body);
        self::assertSame('authorization_code', $body['grant_type']);
        self::assertSame('provider-code', $body['code']);
        self::assertSame('expected-verifier', $body['code_verifier']);
        self::assertSame('https://app.example/callback', $body['redirect_uri']);

        return new MockResponse(json_encode(['id_token' => $token], JSON_THROW_ON_ERROR));
      }

      return new MockResponse(json_encode(['keys' => [$jwk]], JSON_THROW_ON_ERROR));
    });
    $profile = new GenericOidcProviderAdapter($http, $this->providers())->complete(
      ProviderId::from('acme'),
      'https://app.example/callback',
      'provider-code',
      'expected-verifier',
      hash('sha256', 'expected-nonce'),
    );

    self::assertSame([['POST', 'https://issuer.example/token'], ['GET', 'https://issuer.example/keys']], $calls);
    self::assertSame('acme', $profile->provider->value);
    self::assertSame('https://issuer.example', $profile->issuer);
    self::assertSame('external-subject', $profile->subject);
    self::assertSame('person@example.com', $profile->email);
    self::assertTrue($profile->emailVerified);
  }

  /**
   * @return iterable<string, array{array<string, string|bool|null>, bool}>
   */
  public static function invalidTokens(): iterable
  {
    yield 'wrong signature' => [[], true];
    yield 'wrong issuer' => [['iss' => 'https://another.example'], false];
    yield 'wrong audience' => [['aud' => 'different-client'], false];
    yield 'wrong nonce' => [['nonce' => 'another-flow-nonce'], false];
    yield 'missing nonce' => [['nonce' => null], false];
    yield 'expired token' => [['expired' => true], false];
    yield 'future issue time' => [['future_iat' => true], false];
    yield 'future not before' => [['future_nbf' => true], false];
    yield 'missing expiration' => [['missing_exp' => true], false];
    yield 'missing subject' => [['sub' => ''], false];
    yield 'wrong authorized party' => [['azp' => 'other-client'], false];
    yield 'ambiguous audiences' => [['multi_aud' => true], false];
  }

  /**
   * @param array<string, string|bool|null> $changes intentional proof defects
   */
  #[Test]
  #[DataProvider('invalidTokens')]
  public function itRejectsInvalidSignedIdentityProofs(array $changes, bool $wrongSignature): void
  {
    [$privateKey, $jwk] = $this->keyPair();
    $signingKey = $wrongSignature ? $this->keyPair()[0] : $privateKey;
    $http = new MockHttpClient([
      new MockResponse(json_encode(['id_token' => $this->token($signingKey, $changes)], JSON_THROW_ON_ERROR)),
      new MockResponse(json_encode(['keys' => [$jwk]], JSON_THROW_ON_ERROR)),
    ]);
    $this->expectException(FederatedAuthException::class);
    $this->expectExceptionMessage('invalid identity');

    new GenericOidcProviderAdapter($http, $this->providers())->complete(ProviderId::from('acme'), 'https://app.example/callback', 'code', 'verifier', hash('sha256', 'expected-nonce'));
  }

  #[Test]
  public function itRejectsUserInfoFromAnotherSubject(): void
  {
    [$privateKey, $jwk] = $this->keyPair();
    $http = new MockHttpClient([
      new MockResponse(json_encode(['id_token' => $this->token($privateKey), 'access_token' => 'transient-token'], JSON_THROW_ON_ERROR)),
      new MockResponse(json_encode(['keys' => [$jwk]], JSON_THROW_ON_ERROR)),
      new MockResponse(json_encode(['sub' => 'another-subject', 'email' => 'attacker@example.com', 'email_verified' => true], JSON_THROW_ON_ERROR)),
    ]);
    $providers = $this->providers();
    $providers['acme']['userinfo_endpoint'] = 'https://issuer.example/userinfo';
    $this->expectException(FederatedAuthException::class);

    new GenericOidcProviderAdapter($http, $providers)->complete(ProviderId::from('acme'), 'https://app.example/callback', 'code', 'verifier', hash('sha256', 'expected-nonce'));
  }

  #[Test]
  public function itRequiresTheRetainedNonceProofBeforeExchangingACode(): void
  {
    $adapter = new GenericOidcProviderAdapter(new MockHttpClient(static function (): never {
      self::fail('A missing nonce proof must deny before exchanging the authorization code.');
    }), $this->providers());
    $this->expectException(FederatedAuthException::class);

    $adapter->complete(ProviderId::from('acme'), 'https://app.example/callback', 'code', 'verifier');
  }

  #[Test]
  public function itAcceptsSubjectMatchedUserInfoAfterVerifyingTheIdToken(): void
  {
    [$privateKey, $jwk] = $this->keyPair();
    $http = new MockHttpClient([
      new MockResponse(json_encode(['id_token' => $this->token($privateKey), 'access_token' => 'transient-token'], JSON_THROW_ON_ERROR)),
      new MockResponse(json_encode(['keys' => [$jwk]], JSON_THROW_ON_ERROR)),
      new MockResponse(json_encode(['sub' => 'external-subject', 'email' => 'updated@example.com', 'email_verified' => true], JSON_THROW_ON_ERROR)),
    ]);
    $providers = $this->providers();
    $providers['acme']['userinfo_endpoint'] = 'https://issuer.example/userinfo';
    $profile = new GenericOidcProviderAdapter($http, $providers)->complete(ProviderId::from('acme'), 'https://app.example/callback', 'code', 'verifier', hash('sha256', 'expected-nonce'));

    self::assertSame('external-subject', $profile->subject);
    self::assertSame('updated@example.com', $profile->email);
    self::assertTrue($profile->emailVerified);
    self::assertSame('https://issuer.example', $profile->issuer);
  }

  /**
   * Method emailVerificationClaims
   *
   * Provides subject-matched UserInfo claims whose address verification may differ.
   *
   * @access public
   *
   * @return iterable<string, array{array<string, string|bool|null>, string, bool, bool}> claims, resolved address, its verification and initial verification
   */
  public static function emailVerificationClaims(): iterable
  {
    yield 'replacement address without proof' => [['email' => 'updated@example.com'], 'updated@example.com', false, true];
    yield 'identical address retains signed proof' => [['email' => 'person@example.com'], 'person@example.com', true, true];
    yield 'identical address explicitly unverified' => [['email' => 'person@example.com', 'email_verified' => false], 'person@example.com', false, true];
    yield 'replacement address explicitly unverified' => [['email' => 'updated@example.com', 'email_verified' => false], 'updated@example.com', false, true];
    yield 'replacement address explicitly verified' => [['email' => 'updated@example.com', 'email_verified' => true], 'updated@example.com', true, true];
    yield 'replacement address with null proof' => [['email' => 'updated@example.com', 'email_verified' => null], 'updated@example.com', false, true];
    yield 'signed address explicitly unverified' => [['email_verified' => false], 'person@example.com', false, true];
    yield 'signed address retained without UserInfo email claims' => [[], 'person@example.com', true, true];
    yield 'isolated proof cannot verify signed address' => [['email_verified' => true], 'person@example.com', false, false];
    yield 'isolated proof retains original verification' => [['email_verified' => true], 'person@example.com', true, true];
    yield 'paired proof can verify identical signed address' => [['email' => 'person@example.com', 'email_verified' => true], 'person@example.com', true, false];
  }

  /**
   * Method itBindsEmailVerificationToTheResolvedAddress
   *
   * Prevents a verified ID-token address from certifying a different UserInfo address.
   *
   * @access public
   *
   * @param array<string, string|bool|null> $userinfo subject-matched profile overrides
   * @param string $expectedEmail final address returned by the provider
   * @param bool $expectedVerified verification belonging to that address
   * @param bool $idTokenVerified original address verification in the signed ID token
   *
   * @return void
   */
  #[Test]
  #[DataProvider('emailVerificationClaims')]
  public function itBindsEmailVerificationToTheResolvedAddress(array $userinfo, string $expectedEmail, bool $expectedVerified, bool $idTokenVerified): void
  {
    $profile = $this->adapterWithUserInfo($userinfo, $idTokenVerified)->complete(ProviderId::from('acme'), 'https://app.example/callback', 'code', 'verifier', hash('sha256', 'expected-nonce'));

    self::assertSame('external-subject', $profile->subject);
    self::assertSame('https://issuer.example', $profile->issuer);
    self::assertSame($expectedEmail, $profile->email);
    self::assertSame($expectedVerified, $profile->emailVerified);
  }

  /**
   * Method itRejectsProvisioningWhenUserInfoReplacesTheVerifiedAddressWithoutProof
   *
   * Runs the signed-token exchange through the authentication service's email gate.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function itRejectsProvisioningWhenUserInfoReplacesTheVerifiedAddressWithoutProof(): void
  {
    $provider = ProviderId::from('acme');
    $flows = $this->createMock(FederatedFlowRepositoryPort::class);
    $flows->expects(self::once())->method('consume')->with('state', 'browser', $provider, 'login')->willReturn(new FederatedFlow(
      stateHash: hash('sha256', 'state'),
      browserBindingHash: hash('sha256', 'browser'),
      provider: $provider,
      intent: 'login',
      userId: null,
      codeVerifier: 'verifier',
      redirectUri: 'https://app.example/callback',
      returnUrl: '/dashboard',
      expiresAt: new DateTimeImmutable('+10 minutes'),
      nonceHash: hash('sha256', 'expected-nonce'),
    ));
    $flows->expects(self::never())->method('recordVerified');
    $identities = $this->createMock(FederatedIdentityRepositoryPort::class);
    $identities->expects(self::once())->method('hasIssuerConflict')->with($provider, 'https://issuer.example')->willReturn(false);
    $identities->expects(self::never())->method('findBySubject');
    $identities->expects(self::never())->method('save');
    $users = $this->createMock(FederatedUserPort::class);
    $users->expects(self::never())->method('provision');
    $users->expects(self::never())->method('recordSuccessfulLogin');
    $session = $this->createMock(InteractiveSessionIssuerPort::class);
    $session->expects(self::never())->method('issue');
    $transaction = $this->createMock(TransactionManagerPort::class);
    $transaction->expects(self::never())->method('transactional');
    $service = new FederatedAuthenticationService(
      $this->adapterWithUserInfo(['email' => 'updated@example.com']),
      $flows,
      $identities,
      $transaction,
      $users,
      $session,
      'https://app.example',
      $this->createStub(AuthenticationPolicyPort::class),
    );

    try {
      $service->completeLogin($provider, 'state', 'code', 'browser', null, new FederatedLoginContext(null, null, null));
      self::fail('An unverified replacement address must not provision an account or issue a session.');
    } catch (FederatedAuthException $exception) {
      self::assertSame('email_unverified', $exception->errorCode);
    }
  }

  #[Test]
  public function itRejectsUnknownAndDisabledProvidersWithoutHttp(): void
  {
    $providers = $this->providers();
    $providers['acme']['enabled'] = false;
    $adapter = new GenericOidcProviderAdapter(new MockHttpClient(static function (): never {
      self::fail('Unavailable providers must never issue HTTP requests.');
    }), $providers);
    self::assertFalse($adapter->isEnabled(ProviderId::from('unknown')));
    self::assertFalse($adapter->isEnabled(ProviderId::from('acme')));
    $this->expectException(FederatedAuthException::class);

    $adapter->start(ProviderId::from('acme'), 'https://app.example/callback', 'state');
  }

  #[Test]
  public function itRejectsUntrustedHttpConfiguration(): void
  {
    $providers = $this->providers();
    $providers['acme']['token_endpoint'] = 'http://untrusted.example/token';
    $this->expectException(InvalidArgumentException::class);

    new GenericOidcProviderAdapter(new MockHttpClient(), $providers);
  }

  #[Test]
  public function itRejectsReplacementOfALegacyProviderIdentifier(): void
  {
    $this->expectException(InvalidArgumentException::class);

    new GenericOidcProviderAdapter(new MockHttpClient(), ['google' => $this->providers()['acme']]);
  }

  #[Test]
  public function itKeepsLegacyProviderMetadataAlongsideGenericProviders(): void
  {
    $registry = new ConfiguredFederatedProviderRegistryAdapter(
      new LeagueFederatedProviderAdapter(true, 'google-client', 'google-secret', true, 'microsoft-client', 'microsoft-secret'),
      new GenericOidcProviderAdapter(new MockHttpClient(), $this->providers()),
    );
    self::assertSame(['google', 'microsoft', 'acme'], array_map(static fn ($descriptor): string => $descriptor->id->value, $registry->descriptors()));
    self::assertTrue($registry->isEnabled(ProviderId::from('google')));
    self::assertTrue($registry->isEnabled(ProviderId::from('microsoft')));
    self::assertTrue($registry->isEnabled(ProviderId::from('acme')));
    self::assertFalse($registry->isEnabled(ProviderId::from('unknown')));
  }

  /**
   * @return array<string, array<string, mixed>> server-owned example configuration
   */
  private function providers(): array
  {
    return ['acme' => [
      'label' => 'Acme identity', 'enabled' => true, 'issuer' => 'https://issuer.example',
      'authorization_endpoint' => 'https://issuer.example/authorize', 'token_endpoint' => 'https://issuer.example/token',
      'jwks_uri' => 'https://issuer.example/keys', 'client_id' => 'configured-client', 'client_secret' => 'test-client-secret',
    ]];
  }

  /**
   * Method adapterWithUserInfo
   *
   * Signs a fresh ID token and provides matching configured JWKS and UserInfo responses.
   *
   * @access private
   *
   * @param array<string, string|bool|null> $userinfo profile overrides for the verified subject
   * @param bool $idTokenVerified verification of the original signed email address
   *
   * @return GenericOidcProviderAdapter configured provider with real ephemeral RSA proof
   */
  private function adapterWithUserInfo(array $userinfo, bool $idTokenVerified = true): GenericOidcProviderAdapter
  {
    [$privateKey, $jwk] = $this->keyPair();
    $http = new MockHttpClient([
      new MockResponse(json_encode(['id_token' => $this->token($privateKey, ['email_verified' => $idTokenVerified]), 'access_token' => 'transient-token'], JSON_THROW_ON_ERROR)),
      new MockResponse(json_encode(['keys' => [$jwk]], JSON_THROW_ON_ERROR)),
      new MockResponse(json_encode(['sub' => 'external-subject', ...$userinfo], JSON_THROW_ON_ERROR)),
    ]);
    $providers = $this->providers();
    $providers['acme']['userinfo_endpoint'] = 'https://issuer.example/userinfo';

    return new GenericOidcProviderAdapter($http, $providers);
  }

  /**
   * @return array{non-empty-string, array<string, string>} ephemeral private signing key and corresponding public JWK
   */
  private function keyPair(): array
  {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    self::assertNotFalse($key);
    self::assertTrue(openssl_pkey_export($key, $privateKey));
    self::assertIsString($privateKey);
    if ('' === $privateKey) {
      self::fail('The signing fixture requires a non-empty privateKey.');
    }
    $details = openssl_pkey_get_details($key);
    self::assertIsArray($details);

    $rsa = $details['rsa'];
    self::assertIsArray($rsa);
    $modulus = $rsa['n'];
    $exponent = $rsa['e'];
    self::assertIsString($modulus);
    self::assertIsString($exponent);

    return [$privateKey, ['kty' => 'RSA', 'kid' => 'signing-key', 'alg' => 'RS256', 'use' => 'sig', 'n' => $this->base64Url($modulus), 'e' => $this->base64Url($exponent)]];
  }

  /**
   * @param non-empty-string $privateKey ephemeral signing material
   * @param array<string, string|bool|null> $changes intentional proof defects
   */
  private function token(string $privateKey, array $changes = []): string
  {
    $configuration = Configuration::forAsymmetricSigner(new Sha256(), InMemory::plainText($privateKey), InMemory::plainText('unused-public-key'));
    $now = new DateTimeImmutable();
    $issuer = $changes['iss'] ?? 'https://issuer.example';
    $audience = $changes['aud'] ?? 'configured-client';
    $subject = $changes['sub'] ?? 'external-subject';
    self::assertIsString($issuer);
    if ('' === $issuer) {
      self::fail('The signing fixture requires a non-empty issuer.');
    }
    self::assertIsString($audience);
    if ('' === $audience) {
      self::fail('The signing fixture requires a non-empty audience.');
    }
    self::assertIsString($subject);
    $builder = $configuration->builder()
      ->withHeader('kid', 'signing-key')
      ->issuedBy($issuer)
      ->permittedFor($audience)
      ->issuedAt(isset($changes['future_iat']) ? $now->modify('+1 hour') : $now->modify('-1 minute'))
      ->withClaim('email', 'person@example.com')
      ->withClaim('email_verified', $changes['email_verified'] ?? true)
      ->withClaim('given_name', 'Test')
      ->withClaim('family_name', 'Person');
    if ('' !== $subject) {
      $builder = $builder->relatedTo($subject);
    }
    if (!isset($changes['missing_exp'])) {
      $builder = $builder->expiresAt(isset($changes['expired']) ? $now->modify('-1 minute') : $now->modify('+5 minutes'));
    }
    if (!array_key_exists('nonce', $changes) || null !== $changes['nonce']) {
      $builder = $builder->withClaim('nonce', $changes['nonce'] ?? 'expected-nonce');
    }
    if (isset($changes['azp'])) {
      $builder = $builder->withClaim('azp', $changes['azp']);
    }
    if (isset($changes['multi_aud'])) {
      $builder = $builder->permittedFor('another-client');
    }
    if (isset($changes['future_nbf'])) {
      $builder = $builder->canOnlyBeUsedAfter($now->modify('+1 hour'));
    }

    return $builder->getToken($configuration->signer(), $configuration->signingKey())->toString();
  }

  private function base64Url(string $value): string
  {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
  }
}
