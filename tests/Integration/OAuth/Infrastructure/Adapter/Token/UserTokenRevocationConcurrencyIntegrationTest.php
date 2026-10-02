<?php

declare(strict_types=1);

namespace Tests\Integration\OAuth\Infrastructure\Adapter\Token;

use Auth\Infrastructure\Adapter\Session\SessionStatusAdapter;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use OAuth\Domain\Model\Token\{AccessToken, RefreshToken};
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Infrastructure\OAuth2\League\Repository\{AuthCodeRepositoryAdapter, RefreshTokenRepositoryAdapter};
use OAuth\Infrastructure\Persistence\Doctrine\Record\AuthCodeRecord;
use OAuth\Infrastructure\Persistence\Doctrine\Repository\{AccessTokenRepository, AuthCodeRepository, GrantLifecycleRepository, RefreshTokenRepository, UserTokenRevocationRepository};
use OAuth\Presentation\Api\Service\AuthorizationGrantCompletion;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use RuntimeException;
use Session\Application\Service\SessionStatusService;
use Session\Application\UseCase\Query\Session\GetSessionByAccessToken\GetSessionByAccessTokenHandler;
use Session\Infrastructure\Persistence\Doctrine\Repository\SessionRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\{Request, Response};

use function is_int;
use function is_string;

/**
 * Class UserTokenRevocationConcurrencyIntegrationTest
 *
 * Interleaves real PostgreSQL connections at the validated-code/issuance boundary.
 *
 * @category Integration Test
 */
final class UserTokenRevocationConcurrencyIntegrationTest extends KernelTestCase
{
  // #region Methods
  /**
   * Method testBulkRevocationCannotCompleteBetweenCodeValidationAndTokenPersistence
   *
   * Bulk revocation must wait for a validated grant to finish so its new token pair is included.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  #[DataProvider('grantFamilies')]
  public function testBulkRevocationCannotCompleteBetweenGrantValidationAndTokenPersistence(string $family): void
  {
    self::bootKernel();
    $configuredManager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $configuredManager);
    $url = $_ENV['AUTH_DATABASE_URL'] ?? $_SERVER['AUTH_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $issuingConnection = DriverManager::getConnection(['url' => $url]);
    $revokingConnection = DriverManager::getConnection(['url' => $url]);
    $issuingManager = new EntityManager($issuingConnection, $configuredManager->getConfiguration());
    $revokingManager = new EntityManager($revokingConnection, $configuredManager->getConfiguration());
    $revokingConnection->executeStatement("SET lock_timeout = '150ms'");
    $issuingConnection->insert('auth_codes', [
      'identifier' => 'race-code', 'client_identifier' => 'race-client', 'user_identifier' => 'race-user',
      'scopes' => '["READ"]', 'expiry' => '2099-01-01 00:00:00', 'is_revoked' => 0,
    ]);

    $issuingConnection->insert('access_tokens', ['identifier' => 'race-old-access', 'client_identifier' => 'race-client', 'user_identifier' => 'race-user', 'scopes' => '["READ"]', 'expiry' => '2099-01-01 00:00:00', 'is_revoked' => 0]);
    $issuingConnection->insert('refresh_tokens', ['identifier' => 'race-old-refresh', 'access_token_identifier' => 'race-old-access', 'client_identifier' => 'race-client', 'expiry' => '2099-01-01 00:00:00', 'is_revoked' => 0]);

    try {
      $issuingConnection->beginTransaction();
      $codes = new AuthCodeRepositoryAdapter(new AuthCodeRepository($issuingManager, 'test-encryption-key'), new GrantLifecycleRepository($issuingManager));
      $refresh = new RefreshTokenRepositoryAdapter(new RefreshTokenRepository($issuingManager, 'test-encryption-key'), new GrantLifecycleRepository($issuingManager));
      self::assertFalse('authorization_code' === $family ? $codes->isAuthCodeRevoked('race-code') : $refresh->isRefreshTokenRevoked('race-old-refresh'));

      try {
        new UserTokenRevocationRepository($revokingManager, new GrantLifecycleRepository($revokingManager))->revokeForUser('race-user');
        self::fail('Bulk revocation must wait until the already-validated grant has persisted its token pair.');
      } catch (DriverException $exception) {
        self::assertSame('55P03', $exception->getSQLState());
      }
      $access = new AccessToken('race-access', new OAuthClientIdentifier('race-client'), new DateTimeImmutable('+1 hour'), Scopes::fromArray(['READ']), 'race-user');
      new AccessTokenRepository($issuingManager)->save($access);
      new RefreshTokenRepository($issuingManager, 'test-encryption-key')->save(new RefreshToken('race-refresh', new DateTimeImmutable('+1 hour'), 'race-access', new OAuthClientIdentifier('race-client')));
      $issuingConnection->commit();

      $revokingManager = new EntityManager($revokingConnection, $configuredManager->getConfiguration());
      new UserTokenRevocationRepository($revokingManager, new GrantLifecycleRepository($revokingManager))->revokeForUser('race-user');
      $issuingManager->clear();
      $persistedAccess = new AccessTokenRepository($issuingManager)->find('race-access');
      self::assertNotNull($persistedAccess);
      self::assertTrue($persistedAccess->isRevoked());
      $persistedRefresh = new RefreshTokenRepository($issuingManager, 'test-encryption-key')->find('race-refresh');
      self::assertNotNull($persistedRefresh);
      self::assertTrue($persistedRefresh->isRevoked());
    } finally {
      if ($issuingConnection->isTransactionActive()) {
        $issuingConnection->rollBack();
      }
      $issuingConnection->executeStatement("DELETE FROM refresh_tokens WHERE identifier IN ('race-refresh', 'race-old-refresh')");
      $issuingConnection->executeStatement("DELETE FROM access_tokens WHERE identifier IN ('race-access', 'race-old-access')");
      $issuingConnection->executeStatement("DELETE FROM auth_codes WHERE identifier = 'race-code'");
      $issuingManager->close();
      $revokingManager->close();
      $issuingConnection->close();
      $revokingConnection->close();
    }
  }

  /**
   * Method testAuthorizationCompletionSerializesWithBulkRevocationAndRechecksTheAnchor
   *
   * Drives the shared GET/POST completion guard with independent connections and a previously authenticated principal.
   *
   * @return void
   */
  #[Test]
  #[DataProvider('authorizationOrigins')]
  public function testAuthorizationCompletionSerializesWithBulkRevocationAndRechecksTheAnchor(string $path, string $family): void
  {
    self::bootKernel();
    $configuredManager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $configuredManager);
    $url = $_ENV['AUTH_DATABASE_URL'] ?? $_SERVER['AUTH_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $issuingConnection = DriverManager::getConnection(['url' => $url]);
    $revokingConnection = DriverManager::getConnection(['url' => $url]);
    $issuingManager = new EntityManager($issuingConnection, $configuredManager->getConfiguration());
    $revokingManager = new EntityManager($revokingConnection, $configuredManager->getConfiguration());
    $revokingConnection->executeStatement("SET lock_timeout = '150ms'");
    $userId = '22222222-2222-4222-8222-222222222222';
    $sessionId = '33333333-3333-4333-8333-333333333333';
    $issuingConnection->insert('access_tokens', ['identifier' => 'completion-principal', 'client_identifier' => 'race-client', 'user_identifier' => $userId, 'scopes' => '["ADMIN"]', 'expiry' => '2099-01-01 00:00:00', 'is_revoked' => 0]);
    $issuingConnection->insert('sessions', [
      'id' => $sessionId, 'user_id' => $userId, 'access_token_id' => 'completion-session-token',
      'ip_address' => '127.0.0.1', 'user_agent' => 'Controlled concurrency test', 'metadata' => '{}',
      'created_at' => '2026-01-01 00:00:00', 'last_activity_at' => '2026-01-01 00:00:00',
    ]);
    $sessions = new SessionStatusAdapter(new SessionStatusService(new GetSessionByAccessTokenHandler(new SessionRepository($issuingManager))));
    $guard = new AuthorizationGrantCompletion(new GrantLifecycleRepository($issuingManager), $sessions);
    $request = Request::create($path, '/api/oauth2/authorize' === $path ? 'GET' : 'POST');
    $request->attributes->set('_fireguard_verified_token_id', 'auth_session' === $family ? 'completion-session-token' : 'completion-principal');
    $request->attributes->set('_fireguard_verified_token_use', $family);

    try {
      // Populate the identity map before the other connection revokes the issuing session.
      self::assertSame($sessionId, $sessions->activeSessionId('completion-session-token', $userId));
      $response = $guard->complete($request, $userId, function () use ($issuingManager, &$revokingManager, $userId): Response {
        self::assertSame(1, new SessionRepository($revokingManager)->revokeAllForUser($userId));

        try {
          new UserTokenRevocationRepository($revokingManager, new GrantLifecycleRepository($revokingManager))->revokeForUser($userId);
          self::fail('Bulk revocation must wait until authorization completion commits its new code.');
        } catch (DriverException $exception) {
          self::assertSame('55P03', $exception->getSQLState());
        }
        $record = new AuthCodeRecord();
        $record->identifier = 'completion-code';
        $record->clientIdentifier = 'race-client';
        $record->userIdentifier = $userId;
        $record->scopes = ['READ'];
        $record->nonce = 'controlled-nonce';
        $record->expiry = new DateTimeImmutable('+1 hour');
        $issuingManager->persist($record);

        return new Response(status: Response::HTTP_FOUND);
      });
      self::assertSame(302, $response->getStatusCode());
      $revokingManager = new EntityManager($revokingConnection, $configuredManager->getConfiguration());
      new UserTokenRevocationRepository($revokingManager, new GrantLifecycleRepository($revokingManager))->revokeForUser($userId);
      self::assertSame('controlled-nonce', $issuingConnection->fetchOne("SELECT nonce FROM auth_codes WHERE identifier = 'completion-code' AND is_revoked = true"));
      $resumedResponse = $guard->complete($request, $userId, static function (): Response {
        self::fail('A previously authenticated but now revoked principal cannot persist consent or a new code.');
      });
      self::assertSame(401, $resumedResponse->getStatusCode());
      $codeCount = $issuingConnection->fetchOne('SELECT COUNT(*) FROM auth_codes WHERE user_identifier = ?', [$userId]);
      self::assertTrue(is_int($codeCount) || is_string($codeCount));
      self::assertSame(1, (int) $codeCount);
    } finally {
      $issuingConnection->executeStatement("DELETE FROM auth_codes WHERE identifier = 'completion-code'");
      $issuingConnection->executeStatement("DELETE FROM access_tokens WHERE identifier = 'completion-principal'");
      $issuingConnection->executeStatement('DELETE FROM sessions WHERE id = ?', [$sessionId]);
      $issuingManager->close();
      $revokingManager->close();
      $issuingConnection->close();
      $revokingConnection->close();
    }
  }

  /**
   * Method authorizationOrigins
   *
   * @return array<string, array{string, string}>
   */
  public static function authorizationOrigins(): array
  {
    return [
      'GET from interactive session' => ['/api/oauth2/authorize', 'auth_session'],
      'POST consent from interactive session' => ['/api/oauth2/consent/grant', 'auth_session'],
      'GET from OAuth token' => ['/api/oauth2/authorize', 'oauth'],
      'POST consent from OAuth token' => ['/api/oauth2/consent/grant', 'oauth'],
    ];
  }

  /**
   * Method grantFamilies
   *
   * @return array<string, array{string}>
   */
  public static function grantFamilies(): array
  {
    return ['authorization code' => ['authorization_code'], 'refresh token' => ['refresh_token']];
  }

  /**
   * Method testRolledBackIssuanceCannotBeResurrectedByALaterFlush
   *
   * @return void
   */
  #[Test]
  public function testRolledBackIssuanceCannotBeResurrectedByALaterFlush(): void
  {
    self::bootKernel();
    $manager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    $lifecycle = new GrantLifecycleRepository($manager);

    try {
      $lifecycle->transactional(function () use ($manager): void {
        new AccessTokenRepository($manager)->save(new AccessToken('rolled-back-token', new OAuthClientIdentifier('rollback-client'), new DateTimeImmutable('+1 hour'), Scopes::fromArray(['READ']), 'rollback-user'));

        throw new RuntimeException('Controlled issuance failure.');
      });
    } catch (RuntimeException $exception) {
      self::assertSame('Controlled issuance failure.', $exception->getMessage());
    }
    $manager->flush();
    self::assertFalse($manager->getConnection()->fetchOne("SELECT identifier FROM access_tokens WHERE identifier = 'rolled-back-token'"));
  }
  // #endregion
}
