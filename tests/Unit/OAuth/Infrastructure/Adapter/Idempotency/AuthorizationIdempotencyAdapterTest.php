<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Port\Outbound\SessionAuthorizationProofPort;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Application\Contract\Token\{AuthorizationGrantOutcome, AuthorizationReceiptRequest};
use OAuth\Infrastructure\Adapter\Idempotency\AuthorizationIdempotencyAdapter;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Idempotency\{IdempotencyBegin, IdempotencyDeniedException, IdempotencyLease, IdempotencyProof, IdempotencyScope, IdempotencySnapshot};
use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyPort, IdempotencyFingerprintPort};
use Shared\Application\Port\Outbound\RootTransactionManagerPort;
use User\Application\Contract\User\UserSecurityProof;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function json_encode;
use function str_contains;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/**
 * Class AuthorizationIdempotencyAdapterTest
 *
 * Pins authorization of the selected native receipt before its protocol result opens.
 *
 * @category Adapter Tests
 */
final class AuthorizationIdempotencyAdapterTest extends TestCase
{
  // #region Methods
  /**
   * Method selectedCurrentCodeRestoresTheOriginalRedirectWithoutIssuingAnotherGrant
   *
   * @access public
   *
   * @param bool $reconcile whether the selected result follows an uncertain commit
   *
   * @return void
   */
  #[Test]
  #[DataProvider('replayPaths')]
  public function selectedCurrentCodeRestoresTheOriginalRedirectWithoutIssuingAnotherGrant(bool $reconcile): void
  {
    $opened = false;
    $codeChecks = 0;
    $ownerChecks = 0;
    $adapter = $this->adapter($reconcile, $this->proof(), new UserSecurityProof('user', 3, true, true), 7, 'current-grants', 'selected-code', $opened, $codeChecks);

    $result = $adapter->complete($this->request(), static function (): AuthorizationGrantOutcome { self::fail('A selected completed receipt must not issue another grant.'); }, static function () use (&$ownerChecks): bool {
      ++$ownerChecks;

      return true;
    });

    self::assertTrue($opened);
    self::assertSame(1, $codeChecks);
    self::assertSame($reconcile ? 2 : 1, $ownerChecks);
    self::assertSame(302, $result->statusCode);
    self::assertSame('https://client.example/callback?code=original-code', $result->redirectLocation);
    self::assertTrue($result->replayed);
  }

  /**
   * Method selectedReceiptWithLostOwnerOrConsumedCodeDeniesBeforeResultOpening
   *
   * @access public
   *
   * @param bool $reconcile whether the selected result follows an uncertain commit
   * @param array<string, string> $changes selected proof changes
   * @param UserSecurityProof|null $freshUser account state when the selected result is authorized
   * @param int|false $freshClient active client revision when the selected result is authorized
   * @param string $fingerprint current owner grant state
   * @param string|false $usableCode current selected code lookup
   *
   * @return void
   */
  #[Test]
  #[DataProvider('deniedReplays')]
  public function selectedReceiptWithLostOwnerOrConsumedCodeDeniesBeforeResultOpening(bool $reconcile, array $changes, ?UserSecurityProof $freshUser, int|false $freshClient, string $fingerprint, string|false $usableCode): void
  {
    $opened = false;
    $codeChecks = 0;
    $adapter = $this->adapter($reconcile, $this->proof($changes), $freshUser, $freshClient, $fingerprint, $usableCode, $opened, $codeChecks);

    try {
      $adapter->complete($this->request(), static function (): AuthorizationGrantOutcome { self::fail('An unauthorized selected receipt must not issue another grant.'); }, static fn (): bool => true);
      self::fail('The selected receipt must be denied before opening its result.');
    } catch (IdempotencyDeniedException $failure) {
      self::assertSame('owner_lost', $failure->reason);
      self::assertFalse($opened);
      if (false === $usableCode) {
        self::assertSame(1, $codeChecks);
      }
    }
  }

  /**
   * Method replayPaths
   *
   * @access public
   *
   * @return iterable<string, array{bool}> completed result selection paths
   */
  public static function replayPaths(): iterable
  {
    yield 'begin selects completed receipt' => [false];
    yield 'reconcile selects completed receipt' => [true];
  }

  /**
   * Method deniedReplays
   *
   * @access public
   *
   * @return iterable<string, array{bool, array<string, string>, UserSecurityProof|null, int|false, string, string|false}> current owner and native-code denials
   */
  public static function deniedReplays(): iterable
  {
    $user = new UserSecurityProof('user', 3, true, true);
    $denials = [
      'another receipt user' => [['user_id' => 'another-user'], $user, 7, 'current-grants', 'selected-code'],
      'another receipt client' => [['client_id' => 'another-client'], $user, 7, 'current-grants', 'selected-code'],
      'account deleted during result selection' => [[], null, 7, 'current-grants', 'selected-code'],
      'account deactivated during result selection' => [[], new UserSecurityProof('user', 3, false, true), 7, 'current-grants', 'selected-code'],
      'account revision changes during result selection' => [[], new UserSecurityProof('user', 4, true, true), 7, 'current-grants', 'selected-code'],
      'client deactivated during result selection' => [[], $user, false, 'current-grants', 'selected-code'],
      'client revision changes during result selection' => [[], $user, 8, 'current-grants', 'selected-code'],
      'grant fingerprint changes during result selection' => [[], $user, 7, 'revoked-grants', 'selected-code'],
      'selected code consumed during result selection' => [[], $user, 7, 'current-grants', false],
    ];
    foreach ([false, true] as $reconcile) {
      foreach ($denials as $name => [$changes, $freshUser, $freshClient, $fingerprint, $usableCode]) {
        yield ($reconcile ? 'reconcile: ' : 'begin: ') . $name => [$reconcile, $changes, $freshUser, $freshClient, $fingerprint, $usableCode];
      }
    }
  }

  /**
   * Method adapter
   *
   * The engine double selects proof at result opening, without a separate proof inspection.
   *
   * @access private
   *
   * @param bool $reconcile whether the selected result follows an uncertain commit
   * @param IdempotencyProof $proof exact selected completed receipt proof
   * @param UserSecurityProof|null $freshUser current selected-result account proof
   * @param int|false $freshClient current selected-result client revision
   * @param string $fingerprint current owner grant fingerprint
   * @param string|false $usableCode current selected authorization-code state
   * @param bool $opened whether selected-result authorization allowed result bytes to open
   * @param int $codeChecks number of lookups for the exact selected authorization code
   *
   * @return AuthorizationIdempotencyAdapter native authorization receipt boundary
   */
  private function adapter(bool $reconcile, IdempotencyProof $proof, ?UserSecurityProof $freshUser, int|false $freshClient, string $fingerprint, string|false $usableCode, bool &$opened, int &$codeChecks): AuthorizationIdempotencyAdapter
  {
    $snapshot = new IdempotencySnapshot(302, json_encode(['version' => 1, 'status' => 302, 'location' => 'https://client.example/callback?code=original-code', 'content' => '', 'content_type' => null], JSON_THROW_ON_ERROR), secret: true);
    $lease = new IdempotencyLease('scope', 'key', 'input', 'owner', new DateTimeImmutable('2030-01-01'));
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::never())->method('inspectProof');
    $engine->expects(self::never())->method('claimWithinTransaction');
    $engine->expects(self::never())->method('beforeCommit');
    $engine->expects(self::never())->method('confirmedRollback');
    $engine->expects(self::once())->method('begin')->willReturnCallback(static function (IdempotencyScope $scope, string $key, mixed $input, ?callable $authorizeReplay = null) use ($reconcile, $lease, $snapshot, $proof, &$opened): IdempotencyBegin {
      self::assertSame('user', $scope->principalId);
      self::assertSame('receipt-key', $key);
      self::assertNotNull($authorizeReplay);
      if ($reconcile) {
        return new IdempotencyBegin(lease: $lease);
      }
      $authorizeReplay($proof);
      $opened = true;

      return new IdempotencyBegin(replay: $snapshot);
    });
    $transaction = $this->createMock(RootTransactionManagerPort::class);
    if ($reconcile) {
      $transaction->expects(self::once())->method('transactional')->willThrowException(new CommitOutcomeUnknownException(new RuntimeException('Connection lost after commit.')));
      $engine->expects(self::once())->method('commitUnknown')->with($lease);
      $engine->expects(self::once())->method('reconcile')->willReturnCallback(static function (IdempotencyLease $selectedLease, ?callable $authorizeReplay = null) use ($lease, $proof, $snapshot, &$opened): IdempotencySnapshot {
        self::assertSame($lease, $selectedLease);
        self::assertNotNull($authorizeReplay);
        $authorizeReplay($proof);
        $opened = true;

        return $snapshot;
      });
    } else {
      $transaction->expects(self::never())->method('transactional');
      $engine->expects(self::never())->method('commitUnknown');
      $engine->expects(self::never())->method('reconcile');
    }
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('fetchAssociative')->with(self::anything(), ['client'])->willReturn(['security_revision' => 7]);
    $connection->method('fetchOne')->willReturnCallback(static function (string $sql, array $params) use ($freshClient, $usableCode, &$codeChecks): int|string|false {
      if (str_contains($sql, 'FROM auth_codes')) {
        self::assertSame(['selected-code', 'user', 'client'], $params);
        self::assertStringContainsString('is_revoked = FALSE AND expiry > CURRENT_TIMESTAMP', $sql);
        ++$codeChecks;

        return $usableCode;
      }
      self::assertSame(['client'], $params);
      self::assertStringContainsString('is_active = TRUE AND deleted_at IS NULL', $sql);

      return $freshClient;
    });
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('getConnection')->willReturn($connection);
    $users = $this->createMock(UserSecurityRevisionPort::class);
    $users->expects(self::exactly(2))->method('current')->with('user')->willReturnOnConsecutiveCalls(new UserSecurityProof('user', 3, true, true), $freshUser);
    $grants = $this->createStub(SessionAuthorizationProofPort::class);
    $grants->method('currentFingerprint')->willReturn($fingerprint);

    return new AuthorizationIdempotencyAdapter($engine, $this->createStub(IdempotencyFingerprintPort::class), $transaction, $entityManager, $users, $grants, str_repeat('k', 32));
  }

  /**
   * Method proof
   *
   * @access private
   *
   * @param array<string, string> $changes selected receipt proof changes
   *
   * @return IdempotencyProof authenticated selected receipt facts
   */
  private function proof(array $changes = []): IdempotencyProof
  {
    return new IdempotencyProof($changes + ['user_id' => 'user', 'security_revision' => '3', 'client_id' => 'client', 'client_revision' => '7', 'auth_code_id' => 'selected-code', 'success' => '1', 'authorization_fingerprint' => 'current-grants'], new DateTimeImmutable('2030-01-01'));
  }

  /**
   * Method request
   *
   * @access private
   *
   * @return AuthorizationReceiptRequest verified browser and PKCE authorization request
   */
  private function request(): AuthorizationReceiptRequest
  {
    return new AuthorizationReceiptRequest('user', 'session-token', 'auth_session', ['client_id' => 'client', 'code_challenge' => str_repeat('a', 43)], ['receipt-key']);
  }
  // #endregion
}
