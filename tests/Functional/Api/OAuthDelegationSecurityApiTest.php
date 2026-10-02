<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use App\Tests\E2E\OAuth2WebTestCase;
use Auth\Application\Port\Outbound\TokenRevocationPort;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Application\Port\Outbound\Token\JwtParserPort;
use OAuth\Application\UseCase\Command\Consent\GrantConsent\GrantConsentCommand;
use OAuth\Infrastructure\Persistence\Doctrine\Record\ClientRecord;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

use function base64_encode;
use function explode;
use function hash;
use function http_build_query;
use function json_encode;
use function parse_str;
use function parse_url;
use function rtrim;
use function str_repeat;
use function strlen;
use function strtr;

use const PHP_URL_QUERY;

/**
 * Class OAuthDelegationSecurityApiTest
 *
 * Drives real authorization-code and refresh grants through HTTP to prove delegation boundaries.
 *
 * @category Functional Test
 */
final class OAuthDelegationSecurityApiTest extends OAuth2WebTestCase
{
  // #region Methods
  public function testRealGrantScopesIntersectWithRbacAndPreserveInteractiveSessions(): void
  {
    $client = static::createClientWithFixtures();
    $actor = $this->authenticateFreshUser($client);
    $identity = $this->grant($client, $actor, 'OPENID EMAIL');
    $this->request($client, 'GET', '/api/me', $identity['access_token']);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    $this->request($client, 'GET', '/api/oauth2/userinfo', $identity['access_token']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $this->request($client, 'GET', '/api/me', $actor['token']);
    self::assertSame(200, $client->getResponse()->getStatusCode());

    $read = $this->grant($client, $actor, 'OPENID READ');
    $this->request($client, 'GET', '/api/me', $read['access_token']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $this->request($client, 'PATCH', '/api/me', $read['access_token'], ['firstName' => 'Delegated']);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    $this->request($client, 'GET', '/api/organizations/11111111-1111-4111-8111-111111111111', $read['access_token']);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    self::assertStringNotContainsString('insufficient_scope', $client->getResponse()->getContent() ?: '');

    $write = $this->grant($client, $actor, 'OPENID WRITE');
    $this->request($client, 'PATCH', '/api/me', $write['access_token'], ['firstName' => 'Delegated']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $this->request($client, 'DELETE', '/api/organizations/11111111-1111-4111-8111-111111111111', $write['access_token']);
    self::assertSame(403, $client->getResponse()->getStatusCode());

    $this->permitClientScopes(['OPENID', 'ADMIN']);
    $admin = $this->grant($client, $actor, 'OPENID ADMIN');
    $this->request($client, 'GET', '/api/clients', $admin['access_token']);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    self::assertStringNotContainsString('insufficient_scope', $client->getResponse()->getContent() ?: '');
    $this->request($client, 'GET', '/api/me', $admin['access_token']);
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  public function testIssuanceAuditContainsOnlyCompleteTokenIdentifier(): void
  {
    $client = static::createClientWithFixtures();
    $actor = $this->authenticateFreshUser($client);
    $tokens = $this->grant($client, $actor, 'OPENID READ');
    /** @var JwtParserPort $parser */
    $parser = static::getContainer()->get(JwtParserPort::class);
    $tokenId = $parser->parse($tokens['access_token'])['jti'] ?? null;
    self::assertIsString($tokenId);
    self::assertSame(80, strlen($tokenId));
    $row = $this->authManager()->getConnection()->fetchAssociative(
      "SELECT subject_id, metadata FROM audit_events WHERE action = 'oauth.token_issued' AND subject_id = ?",
      [$tokenId],
    );
    self::assertIsArray($row);
    self::assertSame($tokenId, $row['subject_id']);
    self::assertStringNotContainsString($tokens['access_token'], (string) json_encode($row));
    self::assertStringNotContainsString($tokens['refresh_token'], (string) json_encode($row));
  }

  public function testBulkRevocationClosesAccessRefreshAndUnusedAuthorizationCode(): void
  {
    $client = static::createClientWithFixtures();
    $actor = $this->authenticateFreshUser($client);
    $tokens = $this->grant($client, $actor, 'OPENID READ');
    $unusedCode = $this->consentAuthorizationCode($client, $actor['token'], 'OPENID READ');
    $this->request($client, 'POST', '/api/oauth2/token/introspect', $actor['token'], ['token' => $tokens['access_token']]);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertTrue($this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}')['active'] ?? false);

    /** @var TokenRevocationPort $revocation */
    $revocation = static::getContainer()->get(TokenRevocationPort::class);
    $revocation->revokeAllUserTokens($actor['userId']);
    $this->request($client, 'GET', '/api/me', $tokens['access_token']);
    self::assertSame(401, $client->getResponse()->getStatusCode());
    $this->request($client, 'POST', '/api/oauth2/token/introspect', $actor['token'], ['token' => $tokens['access_token']]);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertFalse($this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}')['active'] ?? true);
    $this->exchange($client, ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token']]);
    self::assertSame(400, $client->getResponse()->getStatusCode());
    $this->exchange($client, ['grant_type' => 'authorization_code', 'code' => $unusedCode, 'redirect_uri' => 'https://localhost:8080/callback', 'code_verifier' => str_repeat('a', 64)]);
    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  public function testForgedRevocationCannotInvalidateTheRealToken(): void
  {
    $client = static::createClientWithFixtures();
    $actor = $this->authenticateFreshUser($client);
    $tokens = $this->grant($client, $actor, 'OPENID READ');
    /** @var JwtParserPort $parser */
    $parser = static::getContainer()->get(JwtParserPort::class);
    $tokenId = $parser->parse($tokens['access_token'])['jti'] ?? null;
    $forged = $this->base64Url('{"alg":"none"}') . '.' . $this->base64Url((string) json_encode(['jti' => $tokenId, 'sub' => $actor['userId']])) . '.c2lnbmF0dXJl';
    $this->request($client, 'POST', '/api/oauth2/token/revoke', $actor['token'], ['token' => $forged, 'token_type_hint' => 'access_token']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $this->request($client, 'GET', '/api/me', $tokens['access_token']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $this->request($client, 'POST', '/api/oauth2/token/revoke', $actor['token'], ['token' => $tokens['access_token'], 'token_type_hint' => 'access_token']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $this->request($client, 'GET', '/api/me', $tokens['access_token']);
    self::assertSame(401, $client->getResponse()->getStatusCode());
  }

  public function testClientAllowlistAndRefreshCannotExpandDelegation(): void
  {
    $client = static::createClientWithFixtures();
    $actor = $this->authenticateFreshUser($client);
    $this->permitClientScopes(['OPENID']);
    $this->authorize($client, $actor['token'], 'OPENID ADMIN');
    self::assertSame(400, $client->getResponse()->getStatusCode());
    $tokens = $this->grant($client, $actor, 'OPENID');
    $this->exchange($client, ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'scope' => 'OPENID READ']);
    self::assertSame(400, $client->getResponse()->getStatusCode());
    $this->exchange($client, ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'scope' => 'OPENID']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $refreshed = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertIsString($refreshed['access_token'] ?? null);
    $this->request($client, 'GET', '/api/me', $refreshed['access_token']);
    self::assertSame(403, $client->getResponse()->getStatusCode());

    $this->authorize($client, $actor['token'], '');
    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  /**
   * Method testNarrowedRefreshCannotRestoreOidcClaimsOrAuditScopes
   *
   * @return void
   */
  public function testNarrowedRefreshCannotRestoreOidcClaimsOrAuditScopes(): void
  {
    $client = static::createClientWithFixtures();
    $actor = $this->authenticateFreshUser($client);
    $tokens = $this->grant($client, $actor, 'OPENID EMAIL PROFILE READ');
    $this->exchange($client, ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'scope' => 'OPENID']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $narrowed = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame('OPENID', $narrowed['scope'] ?? null);
    self::assertIsString($narrowed['id_token'] ?? null);
    $idTokenJwt = $narrowed['id_token'];
    if ('' === $idTokenJwt) {
      self::fail('The real narrowed grant must return an ID token.');
    }
    /** @var JwtParserPort $parser */
    $parser = static::getContainer()->get(JwtParserPort::class);
    self::assertTrue($parser->validate($narrowed['id_token']));
    $idToken = new \Lcobucci\JWT\Token\Parser(new \Lcobucci\JWT\Encoding\JoseEncoder())->parse($idTokenJwt);
    self::assertInstanceOf(\Lcobucci\JWT\UnencryptedToken::class, $idToken);
    $claims = $idToken->claims()->all();
    self::assertArrayNotHasKey('email', $claims);
    self::assertArrayNotHasKey('name', $claims);
    self::assertIsString($narrowed['refresh_token'] ?? null);
    $this->exchange($client, ['grant_type' => 'refresh_token', 'refresh_token' => $narrowed['refresh_token'], 'scope' => '']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $refreshed = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame('OPENID', $refreshed['scope'] ?? null);
    self::assertIsString($refreshed['access_token'] ?? null);
    $tokenId = $parser->parse($refreshed['access_token'])['jti'] ?? null;
    self::assertIsString($tokenId);
    $metadata = $this->authManager()->getConnection()->fetchOne("SELECT metadata FROM audit_events WHERE action = 'oauth.token_issued' AND subject_id = ?", [$tokenId]);
    self::assertIsString($metadata);
    self::assertSame(['OPENID'], $this->decodeJsonResponse($metadata)['scopes'] ?? null);
    $this->request($client, 'GET', '/api/me', $refreshed['access_token']);
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  public function testConsentPostEnforcesClientAllowlistAndPermitsTheRegisteredGrant(): void
  {
    $client = static::createClientWithFixtures();
    $actor = $this->authenticateFreshUser($client);
    $this->permitClientScopes(['OPENID']);
    $this->consentPost($client, $actor['token'], 'OPENID ADMIN');
    self::assertSame(400, $client->getResponse()->getStatusCode());
    self::assertFalse($this->authManager()->getConnection()->fetchOne('SELECT user_identifier FROM auth_codes WHERE user_identifier = ?', [$actor['userId']]));
    $code = $this->consentAuthorizationCode($client, $actor['token'], 'OPENID');
    $this->exchange($client, ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => 'https://localhost:8080/callback', 'code_verifier' => str_repeat('a', 64)]);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $tokens = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertIsString($tokens['access_token'] ?? null);
    $this->request($client, 'GET', '/api/oauth2/userinfo', $tokens['access_token']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $this->request($client, 'GET', '/api/me', $tokens['access_token']);
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  private function consentAuthorizationCode(KernelBrowser $client, string $sessionToken, string $scope): string
  {
    $this->consentPost($client, $sessionToken, $scope);
    self::assertSame(302, $client->getResponse()->getStatusCode());
    parse_str((string) parse_url($client->getResponse()->headers->get('Location', ''), PHP_URL_QUERY), $parameters);
    self::assertIsString($parameters['code'] ?? null);

    return $parameters['code'];
  }

  private function consentPost(KernelBrowser $client, string $sessionToken, string $scope): void
  {
    $this->request($client, 'POST', '/api/oauth2/consent/grant', $sessionToken, [
      'response_type' => 'code', 'client_id' => self::DEV_CLIENT_ID,
      'redirect_uri' => 'https://localhost:8080/callback', 'scope' => $scope,
      'code_challenge' => $this->base64Url(hash('sha256', str_repeat('a', 64), true)), 'code_challenge_method' => 'S256',
      'approved' => true,
    ]);
  }

  /**
   * @param array{token:string, userId:string} $actor
   *
   * @return array{access_token:string, refresh_token:string}
   */
  private function grant(KernelBrowser $client, array $actor, string $scope): array
  {
    $code = $this->authorizationCode($client, $actor, $scope);
    $this->exchange($client, ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => 'https://localhost:8080/callback', 'code_verifier' => str_repeat('a', 64)]);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $tokens = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertIsString($tokens['access_token'] ?? null);
    self::assertIsString($tokens['refresh_token'] ?? null);

    return ['access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token']];
  }

  /**
   * @param array{token:string, userId:string} $actor
   */
  private function authorizationCode(KernelBrowser $client, array $actor, string $scope): string
  {
    /** @var CommandBusPort $commands */
    $commands = static::getContainer()->get(CommandBusPort::class);
    $commands->dispatch(new GrantConsentCommand($actor['userId'], self::DEV_CLIENT_ID, '' === $scope ? [] : explode(' ', $scope)));
    $this->authorize($client, $actor['token'], $scope);
    self::assertSame(302, $client->getResponse()->getStatusCode());
    parse_str((string) parse_url($client->getResponse()->headers->get('Location', ''), PHP_URL_QUERY), $query);
    self::assertIsString($query['code'] ?? null);

    return $query['code'];
  }

  private function authorize(KernelBrowser $client, string $sessionToken, string $scope): void
  {
    $parameters = ['response_type' => 'code', 'client_id' => self::DEV_CLIENT_ID, 'redirect_uri' => 'https://localhost:8080/callback', 'code_challenge' => $this->base64Url(hash('sha256', str_repeat('a', 64), true)), 'code_challenge_method' => 'S256'];
    if ('' !== $scope) {
      $parameters['scope'] = $scope;
    }
    $client->request('GET', '/api/oauth2/authorize?' . http_build_query($parameters), server: ['HTTP_ACCEPT' => '*/*', 'HTTP_AUTHORIZATION' => 'Bearer ' . $sessionToken]);
  }

  /**
   * @param array<string, string> $grant
   */
  private function exchange(KernelBrowser $client, array $grant): void
  {
    $client->request('POST', '/api/oauth2/token', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => '*/*'], content: (string) json_encode($grant + ['client_id' => self::DEV_CLIENT_ID, 'client_secret' => self::DEV_CLIENT_SECRET]));
  }

  /**
   * @param array<string, string|bool> $body
   */
  private function request(KernelBrowser $client, string $method, string $path, string $token, array $body = []): void
  {
    $client->request($method, $path, server: ['CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/json', 'HTTP_ACCEPT' => '*/*', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token], content: [] === $body ? null : (string) json_encode($body));
  }

  /**
   * @param list<string> $scopes
   */
  private function permitClientScopes(array $scopes): void
  {
    $manager = $this->authManager();
    $record = $manager->find(ClientRecord::class, self::DEV_CLIENT_ID);
    self::assertInstanceOf(ClientRecord::class, $record);
    $record->scopes = $scopes;
    $manager->flush();
  }

  private function authManager(): EntityManagerInterface
  {
    $manager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);

    return $manager;
  }

  private function base64Url(string $value): string
  {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
  }
  // #endregion
}
