<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Application\UseCase\Command\Mfa\MfaVerify\{MfaVerifyCommand, MfaVerifyHandler, MfaVerifyResult};
use Auth\Application\UseCase\Command\Session\Login\{LoginCommand, LoginHandler, LoginResult};
use Auth\Application\UseCase\Command\Session\RefreshToken\{RefreshTokenCommand, RefreshTokenHandler, RefreshTokenResult};
use Auth\Infrastructure\Adapter\Idempotency\{AuthHttpIdempotencyOperationContributionAdapter, LoginIdempotencyProofAdapter, MfaIdempotencyProofAdapter, RefreshIdempotencyProofAdapter};
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Application\Port\Inbound\ClientSecurityProofPort;
use OAuth\Infrastructure\Persistence\Doctrine\Record\ClientRecord;
use Otp\Application\Port\Outbound\Internal\Challenge\OtpRepositoryPort;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{OtpChannel, OtpId, OtpPurpose};
use PHPUnit\Framework\Attributes\WithEnvironmentVariable;
use Shared\Application\Contract\Idempotency\IdempotencyScope;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyPort, SensitiveIdempotencyProofPort};
use Shared\Infrastructure\Idempotency\HttpIdempotencyExecutionContext;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;
use User\Application\Port\Inbound\UserSecurityRevisionPort;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

use function password_hash;

use const PASSWORD_BCRYPT;

/**
 * Proves committed credential/challenge/session outcomes against native PostgreSQL receipts.
 *
 * @category Integration Test
 */
#[SkipDatabaseRollback]
final class SensitiveHttpIdempotencyTest extends KernelTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string USER = 'f00e8400-e29b-41d4-a716-4466554c0801';

  private const string PASSWORD = 'TestPassword-Receipt-123!';

  private Connection $connection;

  private HttpIdempotencyPort $engine;

  private HttpIdempotencyExecutionContext $execution;

  protected function setUp(): void
  {
    static::bootKernel();
    $container = static::getContainer();
    $manager = $container->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    $this->connection = $manager->getConnection();
    $this->connection->insert('users', ['id' => self::USER, 'username' => 'receipt_test', 'email' => 'receipt-test@example.test', 'password' => password_hash(self::PASSWORD, PASSWORD_BCRYPT), 'first_name' => 'Receipt', 'last_name' => 'Test', 'status' => 'active', 'email_verified' => true, 'created_at' => '2026-10-11 12:00:00', 'failed_login_attempts' => 0, 'locale' => 'system', 'security_revision' => 1]);
    $engine = $container->get('shared.http_idempotency.auth');
    self::assertInstanceOf(HttpIdempotencyPort::class, $engine);
    $this->engine = $engine;
    $this->execution = $this->service(HttpIdempotencyExecutionContext::class);
    $requests = $container->get('request_stack');
    self::assertInstanceOf(RequestStack::class, $requests);
    $requests->push(Request::create('/api/auth/test', 'POST'));
  }

  public function testFailedLoginCounterAndFailureReceiptCommitExactlyOnce(): void
  {
    $command = new LoginCommand('receipt-test@example.test', 'incorrect-password');
    $policy = $this->service(LoginIdempotencyProofAdapter::class);
    $handler = $this->service(LoginHandler::class);
    $first = $this->execute('login', $command, $policy, $handler, 'wrong-password');
    self::assertInstanceOf(LoginResult::class, $first);
    self::assertFalse($first->authenticated);
    self::assertSame(1, $this->attempts());
    $second = $this->execute('login', $command, $policy, $handler, 'wrong-password');
    self::assertEquals($first, $second);
    self::assertSame(1, $this->attempts());
    $this->execute('login', $command, $policy, $handler, 'second-attempt');
    self::assertSame(2, $this->attempts());
    self::assertSame(2, $this->connection->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts WHERE state = ?', ['completed']));
    $serialized = $this->connection->fetchOne('SELECT result_payload FROM http_idempotency_receipts LIMIT 1');
    self::assertIsString($serialized);
    self::assertStringNotContainsString($command->password, $serialized);
  }

  public function testOldPasswordCannotRecoverACompletedLoginSecret(): void
  {
    $command = new LoginCommand('receipt-test@example.test', self::PASSWORD);
    $policy = $this->service(LoginIdempotencyProofAdapter::class);
    $handler = $this->service(LoginHandler::class);
    $first = $this->execute('login', $command, $policy, $handler, 'good-login');
    self::assertInstanceOf(LoginResult::class, $first);
    self::assertTrue($first->authenticated);
    self::assertNotNull($first->accessToken);
    $this->connection->executeStatement('UPDATE users SET password = ?, security_revision = security_revision + 1 WHERE id = ?', [password_hash('Replacement-Password-456!', PASSWORD_BCRYPT), self::USER]);
    $this->expectException(UnauthorizedHttpException::class);
    $this->execute('login', $command, $policy, $handler, 'good-login');
  }

  public function testWrongMfaProofPersistsOneFailureAndOneReceiptOnRetry(): void
  {
    $repo = $this->service(OtpRepositoryPort::class);
    $challenge = Otp::generate(new OtpId('f00e8400-e29b-41d4-a716-4466554c0802'), self::USER, OtpPurpose::LOGIN, OtpChannel::EMAIL, 'receipt-test@example.test');
    $repo->save($challenge);
    $tokens = $this->service(JwtTokenServicePort::class);
    $preAuth = $tokens->generatePreAuthToken(self::USER, $challenge->challengeToken()->value, 'receipt-test@example.test', ['READ']);
    $wrong = '000000' === $challenge->code()->plain() ? '111111' : '000000';
    $command = new MfaVerifyCommand($preAuth, $wrong);
    $policy = $this->service(MfaIdempotencyProofAdapter::class);
    $handler = $this->service(MfaVerifyHandler::class);
    $first = $this->execute('mfa_verify', $command, $policy, $handler, 'wrong-mfa');
    self::assertInstanceOf(MfaVerifyResult::class, $first);
    self::assertFalse($first->success);
    self::assertSame(1, $this->connection->fetchOne('SELECT attempts FROM otps WHERE id = ?', [$challenge->id()->value]));
    $second = $this->execute('mfa_verify', $command, $policy, $handler, 'wrong-mfa');
    self::assertEquals($first, $second);
    self::assertSame(1, $this->connection->fetchOne('SELECT attempts FROM otps WHERE id = ?', [$challenge->id()->value]));
    $this->execute('mfa_verify', $command, $policy, $handler, 'another-mfa-attempt');
    self::assertSame(2, $this->connection->fetchOne('SELECT attempts FROM otps WHERE id = ?', [$challenge->id()->value]));
  }

  public function testRefreshReceiptAcceptsParentOrCurrentDaughterAndRejectsRerotation(): void
  {
    $login = $this->execute('login', new LoginCommand('receipt-test@example.test', self::PASSWORD), $this->service(LoginIdempotencyProofAdapter::class), $this->service(LoginHandler::class), 'session-login');
    self::assertInstanceOf(LoginResult::class, $login);
    self::assertNotNull($login->refreshToken);
    $policy = $this->service(RefreshIdempotencyProofAdapter::class);
    $handler = $this->service(RefreshTokenHandler::class);
    $parent = new RefreshTokenCommand($login->refreshToken);
    $first = $this->execute('refresh', $parent, $policy, $handler, 'rotation');
    self::assertInstanceOf(RefreshTokenResult::class, $first);
    self::assertTrue($first->success);
    self::assertNotNull($first->refreshToken);
    $second = $this->execute('refresh', $parent, $policy, $handler, 'rotation');
    self::assertInstanceOf(RefreshTokenResult::class, $second);
    self::assertSame($first->refreshToken, $second->refreshToken);
    $daughter = new RefreshTokenCommand($first->refreshToken);
    $same = $this->execute('refresh', $daughter, $policy, $handler, 'rotation');
    self::assertInstanceOf(RefreshTokenResult::class, $same);
    self::assertSame($first->accessToken, $same->accessToken);
    $rejected = $this->execute('refresh', $parent, $policy, $handler, 'other-key');
    self::assertInstanceOf(RefreshTokenResult::class, $rejected);
    self::assertFalse($rejected->success);
    $rotated = $this->execute('refresh', $daughter, $policy, $handler, 'next-rotation');
    self::assertInstanceOf(RefreshTokenResult::class, $rotated);
    self::assertTrue($rotated->success);
    $this->expectException(UnauthorizedHttpException::class);
    $this->execute('refresh', $parent, $policy, $handler, 'rotation');
  }

  public function testConcurrentStaleUserWriteAdvancesTheStoredSecurityEpoch(): void
  {
    $manager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    $stale = $manager->find(UserRecord::class, self::USER);
    self::assertInstanceOf(UserRecord::class, $stale);
    self::assertSame(1, $stale->securityRevision);
    $other = DriverManager::getConnection($this->connection->getParams());

    try {
      $other->executeStatement('UPDATE users SET password = ? WHERE id = ?', [password_hash('Concurrent-Password-123!', PASSWORD_BCRYPT), self::USER]);
      self::assertSame(2, $other->fetchOne('SELECT security_revision FROM users WHERE id = ?', [self::USER]));
      $stale->password = password_hash('Stale-Proposal-Password-456!', PASSWORD_BCRYPT);
      $stale->securityRevision = 2;
      $manager->flush();
      self::assertSame(3, $other->fetchOne('SELECT security_revision FROM users WHERE id = ?', [self::USER]));
      self::assertSame(2, $stale->securityRevision);
      $current = $this->service(UserSecurityRevisionPort::class)->current(self::USER);
      self::assertNotNull($current);
      self::assertSame(3, $current->securityRevision);
      $other->executeStatement('UPDATE users SET first_name = ?, security_revision = 1 WHERE id = ?', ['Ordinary update', self::USER]);
      self::assertSame(3, $other->fetchOne('SELECT security_revision FROM users WHERE id = ?', [self::USER]));
    } finally {
      $other->close();
    }
  }

  #[WithEnvironmentVariable('ACCESS_TOKEN_INCLUDE_RBAC', 'true')]
  public function testRoleRemovalDeniesReexposureOfTheOriginalPrivilegedJwt(): void
  {
    $roleId = $this->connection->fetchOne('SELECT id FROM roles WHERE name = ?', ['admin']);
    self::assertIsString($roleId);
    $assignment = 'f00e8400-e29b-41d4-a716-4466554c0804';
    $this->connection->insert('role_assignments', ['id' => $assignment, 'role_id' => $roleId, 'subject_type' => 'user', 'subject_id' => self::USER, 'assigned_at' => '2026-10-11 12:00:00']);
    $command = new LoginCommand('receipt-test@example.test', self::PASSWORD);
    $policy = $this->service(LoginIdempotencyProofAdapter::class);
    $handler = $this->service(LoginHandler::class);
    $issued = $this->execute('login', $command, $policy, $handler, 'privileged-login');
    self::assertInstanceOf(LoginResult::class, $issued);
    self::assertTrue($issued->authenticated);
    self::assertNotNull($issued->accessToken);
    $claims = $this->service(JwtTokenServicePort::class)->decodePreAuthToken($issued->accessToken);
    self::assertNotNull($claims);
    self::assertIsArray($claims['roles'] ?? null);
    self::assertContains('admin', $claims['roles']);
    $this->connection->delete('role_assignments', ['id' => $assignment]);
    self::assertSame(1, $this->connection->fetchOne('SELECT security_revision FROM users WHERE id = ?', [self::USER]));
    $this->expectException(UnauthorizedHttpException::class);
    $this->execute('login', $command, $policy, $handler, 'privileged-login');
  }

  public function testConcurrentStaleClientSecretWriteAdvancesTheStoredSecurityEpoch(): void
  {
    $clientId = 'f00e8400-e29b-41d4-a716-4466554c0803';
    $this->connection->insert('clients', ['id' => $clientId, 'name' => 'Receipt client', 'secret' => password_hash('Original-Client-Secret-123!', PASSWORD_BCRYPT), 'redirect_uris' => '[]', 'grant_types' => '["CLIENT_CREDENTIALS"]', 'scopes' => '["READ"]', 'is_active' => true, 'created_at' => '2026-10-11 12:00:00', 'security_revision' => 1]);
    $manager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    $stale = $manager->find(ClientRecord::class, $clientId);
    self::assertInstanceOf(ClientRecord::class, $stale);
    $other = DriverManager::getConnection($this->connection->getParams());

    try {
      $other->executeStatement('UPDATE clients SET secret = ? WHERE id = ?', [password_hash('Concurrent-Client-Secret-456!', PASSWORD_BCRYPT), $clientId]);
      self::assertSame(2, $other->fetchOne('SELECT security_revision FROM clients WHERE id = ?', [$clientId]));
      $stale->secret = password_hash('Stale-Client-Secret-789!', PASSWORD_BCRYPT);
      $stale->securityRevision = 2;
      $manager->flush();
      self::assertSame(3, $other->fetchOne('SELECT security_revision FROM clients WHERE id = ?', [$clientId]));
      $proof = $this->service(ClientSecurityProofPort::class)->verify($clientId, 'Stale-Client-Secret-789!', 'client_credentials');
      self::assertNotNull($proof);
      self::assertSame(3, $proof->securityRevision);
      self::assertSame(2, $stale->securityRevision);
    } finally {
      $other->close();
    }
  }

  /**
   * @template T of CommandMessage
   *
   * @param string $name owner operation
   * @param T $command exact mutation meaning
   * @param SensitiveIdempotencyProofPort $policy current owner proof
   * @param callable(T):ResultMessage $handler real mutation handler
   * @param string $key logical client request
   *
   * @return ResultMessage committed or replayed application outcome
   */
  private function execute(string $name, CommandMessage $command, SensitiveIdempotencyProofPort $policy, callable $handler, string $key): ResultMessage
  {
    $this->execution->deactivate();
    $operation = null;
    foreach (AuthHttpIdempotencyOperationContributionAdapter::definitions() as $candidate) {
      if ($candidate->name === $name) {
        $operation = $candidate;

        break;
      }
    }
    self::assertNotNull($operation);
    $authorization = $policy->authorize($command, null);
    $scope = new IdempotencyScope($authorization->principalId, null, null, $name, '/api/auth/test');
    $stored = $this->engine->inspectProof($scope, $key);
    if (null !== $stored) {
      $authorization = $policy->authorize($command, $stored);
    }
    $begin = $this->engine->begin($scope, $key, $authorization->canonicalInput);
    $this->execution->activate($operation, $this->engine, $begin, $authorization->receiptProof, $policy, $command, $stored);

    return $this->execution->dispatchPrimary($command, static fn (): ResultMessage => $handler($command));
  }

  private function attempts(): int
  {
    $attempts = $this->connection->fetchOne('SELECT failed_login_attempts FROM users WHERE id = ?', [self::USER]);
    self::assertIsInt($attempts);

    return $attempts;
  }

  /**
   * @template T of object
   *
   * @param class-string<T> $class exact owner service
   *
   * @return T real container service
   */
  private function service(string $class): object
  {
    $service = static::getContainer()->get($class);
    self::assertInstanceOf($class, $service);

    return $service;
  }
}
