<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use Auth\Application\Port\Outbound\{AuthenticationPolicyPort, JwtTokenServicePort, SessionAuthorizationProofPort};
use Auth\Application\UseCase\Command\EmailOwnership\ConfirmEmailOwnership\{ConfirmEmailOwnershipCommand, ConfirmEmailOwnershipHandler, ConfirmEmailOwnershipResult};
use Auth\Application\UseCase\Command\Mfa\MfaResend\{MfaResendCommand, MfaResendHandler, MfaResendResult};
use Auth\Application\UseCase\Command\PasswordChange\RequestPasswordChange\{RequestPasswordChangeCommand, RequestPasswordChangeHandler, RequestPasswordChangeResult};
use Auth\Application\UseCase\Command\PasswordReset\ConfirmPasswordReset\{ConfirmPasswordResetCommand, ConfirmPasswordResetHandler, ConfirmPasswordResetResult};
use Auth\Application\UseCase\Command\PasswordReset\RequestPasswordReset\{RequestPasswordResetCommand, RequestPasswordResetHandler};
use Auth\Application\UseCase\Command\PasswordSetup\ConfirmPasswordSetup\{ConfirmPasswordSetupCommand, ConfirmPasswordSetupHandler, ConfirmPasswordSetupResult};
use Auth\Application\UseCase\Command\Registration\ConfirmRegistration\{ConfirmRegistrationCommand, ConfirmRegistrationHandler, ConfirmRegistrationResult};
use Auth\Application\UseCase\Command\Registration\RegisterUser\{RegisterUserCommand, RegisterUserHandler, RegisterUserResult};
use Auth\Infrastructure\Adapter\Idempotency\{AuthCredentialHttpIdempotencyOperationContributionAdapter, CredentialLifecycleIdempotencyProofAdapter, SessionAuthorizationProofAdapter};
use Authorization\Application\Contract\Grant\UserGrantSnapshot;
use Authorization\Application\Port\Inbound\UserGrantSnapshotPort;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\ORM\EntityManagerInterface;
use Otp\Application\Port\Outbound\Internal\Challenge\OtpRepositoryPort;
use Otp\Application\Port\Outbound\Internal\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Application\UseCase\Command\Challenge\GenerateOtp\{GenerateOtpCommand, GenerateOtpHandler, GenerateOtpResult};
use Otp\Application\UseCase\Command\Challenge\ResendChallenge\{ResendChallengeCommand, ResendChallengeHandler, ResendChallengeResult};
use Otp\Application\UseCase\Command\Challenge\VerifyOtp\{VerifyOtpCommand, VerifyOtpHandler, VerifyOtpResult};
use Otp\Application\UseCase\Command\Totp\ConfirmTotp\{ConfirmTotpCommand, ConfirmTotpHandler, ConfirmTotpResult};
use Otp\Application\UseCase\Command\Totp\DisableTotp\{DisableTotpCommand, DisableTotpHandler, DisableTotpResult};
use Otp\Application\UseCase\Command\Totp\GenerateRecoveryCodes\{GenerateRecoveryCodesCommand, GenerateRecoveryCodesHandler, GenerateRecoveryCodesResult};
use Otp\Application\UseCase\Command\Totp\SetupTotp\{SetupTotpCommand, SetupTotpHandler, SetupTotpResult};
use Otp\Domain\Model\{Otp, Totp\TotpEnrollment};
use Otp\Domain\ValueObject\{OtpChannel, OtpId, OtpPurpose, RecoveryCode, TotpSecret};
use Otp\Infrastructure\Adapter\Idempotency\{AuthenticatorIdempotencyProofAdapter, ChallengeIdempotencyProofAdapter, ChallengeLifecycleProofAdapter, OtpAuthenticatorHttpIdempotencyOperationContributionAdapter, OtpChallengeHttpIdempotencyOperationContributionAdapter};
use PHPUnit\Framework\Attributes\{DataProvider, WithEnvironmentVariable};
use RuntimeException;
use Session\Application\Port\Inbound\SessionReplayProofPort;
use Shared\Application\Contract\Http\HttpIdempotencyPrincipal;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyScope};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyPlatformAuthorityPort, HttpIdempotencyPrincipalPort, SensitiveIdempotencyProofPort};
use Shared\Application\Service\Idempotency\DurableHttpIdempotencyService;
use Shared\Infrastructure\Crypto\VersionedAesGcmAdapter;
use Shared\Infrastructure\Idempotency\{HmacIdempotencyFingerprintAdapter, HttpIdempotencyExecutionContext};
use Shared\Infrastructure\Persistence\Doctrine\Repository\DoctrineIdempotencyReceiptRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;
use User\Application\Port\Inbound\UserSecurityRevisionPort;
use User\Infrastructure\Adapter\Idempotency\CredentialLifecycleProofAdapter;

use function base64_encode;
use function password_hash;
use function str_repeat;

use const PASSWORD_BCRYPT;

/**
 * Commits real auth mutations and encrypted receipts, then exercises replay, revocation and original proof expiry.
 *
 * @category Integration Test
 */
#[SkipDatabaseRollback]
final class CredentialLifecycleHttpIdempotencyTest extends KernelTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string USER = 'f00e8400-e29b-41d4-a716-4466554c0901';

  private Connection $connection;

  private Connection $reservation;

  private EntityManagerInterface $manager;

  private DurableHttpIdempotencyService $engine;

  private HttpIdempotencyExecutionContext $execution;

  private DateTimeImmutable $now;

  protected function setUp(): void
  {
    static::bootKernel();
    $container = static::getContainer();
    $manager = $container->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    $this->manager = $manager;
    $this->connection = $this->manager->getConnection();
    $this->connection->insert('users', ['id' => self::USER, 'username' => 'lifecycle_receipt', 'email' => 'lifecycle@example.test', 'password' => password_hash('Original-Password-123!', PASSWORD_BCRYPT), 'first_name' => 'Receipt', 'last_name' => 'Test', 'status' => 'active', 'email_verified' => true, 'created_at' => '2026-10-11 12:00:00', 'failed_login_attempts' => 0, 'locale' => 'system', 'security_revision' => 1]);
    $parameters = $this->connection->getParams();
    unset($parameters['wrapperClass']);
    $this->reservation = DriverManager::getConnection($parameters);
    self::assertNotSame($this->connection->fetchOne('SELECT pg_backend_pid()'), $this->reservation->fetchOne('SELECT pg_backend_pid()'));
    $this->now = new DateTimeImmutable();
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturnCallback(fn (): DateTimeImmutable => $this->now);
    $this->engine = new DurableHttpIdempotencyService(new DoctrineIdempotencyReceiptRepository($this->connection, $this->reservation), new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('h', 32))), new VersionedAesGcmAdapter(['test' => base64_encode(str_repeat('k', 32))], 'test'), $clock);
    $this->execution = $this->service(HttpIdempotencyExecutionContext::class);
    $requests = $container->get('request_stack');
    self::assertInstanceOf(RequestStack::class, $requests);
    $requests->push(Request::create('/api/auth/lifecycle-test', 'POST'));
  }

  protected function tearDown(): void
  {
    $this->reservation->close();
    parent::tearDown();
  }

  public function testSetupReplaysExactSecretAndLaterRotationRevokesItsReceipt(): void
  {
    $command = new SetupTotpCommand(self::USER, 'lifecycle@example.test');
    $handler = $this->service(SetupTotpHandler::class);
    $policy = $this->factorPolicy();
    $first = $this->execute('setupTotp', $command, $policy, $handler, 'setup');
    self::assertInstanceOf(SetupTotpResult::class, $first);
    $second = $this->execute('setupTotp', $command, $policy, $handler, 'setup');
    self::assertEquals($first, $second);
    self::assertSame(2, $this->revision());
    self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM totp_enrollments'));
    $payload = $this->connection->fetchOne('SELECT result_payload FROM http_idempotency_receipts LIMIT 1');
    self::assertIsString($payload);
    self::assertStringNotContainsString($first->secret, $payload);
    $this->execute('setupTotp', $command, $policy, $handler, 'new-setup');
    self::assertSame(3, $this->revision());
    $this->expectException(IdempotencyDeniedException::class);
    $this->execute('setupTotp', $command, $policy, $handler, 'setup');
  }

  public function testRecoveryLotReplaysAfterOriginalTotpExpiresWithoutVerifyingItTwice(): void
  {
    $this->activeFactor();
    $totp = $this->createMock(TotpServicePort::class);
    $totp->expects(self::once())->method('verify')->willReturn(true);
    static::getContainer()->set(TotpServicePort::class, $totp);
    $handler = $this->service(GenerateRecoveryCodesHandler::class);
    $command = new GenerateRecoveryCodesCommand(self::USER, '123456');
    $policy = $this->factorPolicy();
    $first = $this->execute('generateRecoveryCodes', $command, $policy, $handler, 'lot');
    self::assertInstanceOf(GenerateRecoveryCodesResult::class, $first);
    self::assertCount(10, $first->codes);
    $this->now = $this->now->modify('+2 minutes');
    self::assertEquals($first, $this->execute('generateRecoveryCodes', $command, $policy, $handler, 'lot'));
    self::assertSame(2, $this->revision());
    self::assertSame(10, $this->connection->fetchOne('SELECT json_array_length(recovery_code_hashes::json) FROM totp_enrollments WHERE user_id = ?', [self::USER]));
    $this->now = $this->now->modify('+4 minutes');
    $this->assertDenied('expired', fn () => $this->execute('generateRecoveryCodes', $command, $policy, $handler, 'lot'));
    self::assertSame(2, $this->revision());
  }

  public function testConsumedRecoveryDisableProofReplaysAfterFactorRemovalExactlyOnce(): void
  {
    $factor = $this->activeFactor();
    $code = str_repeat('a', 32);
    $factor->replaceRecoveryCodes([RecoveryCode::hash($code) ?? self::fail('Valid test proof')], new DateTimeImmutable());
    $this->service(TotpEnrollmentRepositoryPort::class)->save($factor);
    $command = new DisableTotpCommand(self::USER, $code, 'recovery_code');
    $handler = $this->service(DisableTotpHandler::class);
    $policy = $this->factorPolicy();
    $first = $this->execute('disableTotp', $command, $policy, $handler, 'disable');
    self::assertInstanceOf(DisableTotpResult::class, $first);
    self::assertTrue($first->success);
    self::assertEquals($first, $this->execute('disableTotp', $command, $policy, $handler, 'disable'));
    self::assertSame(2, $this->revision());
    self::assertNull($this->connection->fetchOne('SELECT active_confirmed_at FROM totp_enrollments WHERE user_id = ?', [self::USER]));
    $this->connection->executeStatement("UPDATE users SET status = 'inactive' WHERE id = ?", [self::USER]);
    $this->assertDenied('result_unavailable', fn () => $this->execute('disableTotp', $command, $policy, $handler, 'disable'));
    self::assertSame(3, $this->revision());
  }

  public function testFailedTotpConfirmationAndReceiptCommitOneAttempt(): void
  {
    $this->service(TotpEnrollmentRepositoryPort::class)->save(TotpEnrollment::startEnrollment(self::USER, new TotpSecret('JBSWY3DPEHPK3PXP'), 5));
    $totp = $this->createMock(TotpServicePort::class);
    $totp->expects(self::once())->method('verify')->willReturn(false);
    static::getContainer()->set(TotpServicePort::class, $totp);
    $command = new ConfirmTotpCommand(self::USER, '000000');
    $handler = $this->service(ConfirmTotpHandler::class);
    $policy = $this->factorPolicy();
    $first = $this->execute('confirmTotp', $command, $policy, $handler, 'wrong-totp');
    self::assertInstanceOf(ConfirmTotpResult::class, $first);
    self::assertFalse($first->success);
    self::assertEquals($first, $this->execute('confirmTotp', $command, $policy, $handler, 'wrong-totp'));
    self::assertSame(1, $this->connection->fetchOne('SELECT attempts FROM totp_enrollments WHERE user_id = ?', [self::USER]));
    self::assertSame(1, $this->revision());
  }

  public function testBadMailboxProofCommitsItsCounterAndCanReplayTheSameFailure(): void
  {
    $challenge = $this->challenge(OtpPurpose::EMAIL_OWNERSHIP);
    $command = new ConfirmEmailOwnershipCommand(self::USER, $challenge->challengeToken()->value, '000000' === $challenge->code()->plain() ? '111111' : '000000');
    $handler = $this->service(ConfirmEmailOwnershipHandler::class);
    $policy = $this->credentialPolicy();
    $first = $this->execute('auth_email_ownership_confirm', $command, $policy, $handler, 'wrong-mailbox');
    self::assertInstanceOf(ConfirmEmailOwnershipResult::class, $first);
    self::assertFalse($first->verified);
    self::assertEquals($first, $this->execute('auth_email_ownership_confirm', $command, $policy, $handler, 'wrong-mailbox'));
    self::assertSame(1, $this->connection->fetchOne('SELECT attempts FROM otps WHERE id = ?', [$challenge->id()->value]));
  }

  public function testPasswordResetReceiptAllowsExactConsumedProofAndDeniesLaterCredentialRevision(): void
  {
    $challenge = $this->challenge(OtpPurpose::PASSWORD_RESET);
    $command = new ConfirmPasswordResetCommand($challenge->challengeToken()->value, $challenge->code()->plain(), 'Replacement-Password-456!');
    $handler = $this->service(ConfirmPasswordResetHandler::class);
    $policy = $this->credentialPolicy();
    $first = $this->execute('confirm_password_reset', $command, $policy, $handler, 'reset');
    self::assertInstanceOf(ConfirmPasswordResetResult::class, $first);
    self::assertTrue($first->success);
    self::assertEquals($first, $this->execute('confirm_password_reset', $command, $policy, $handler, 'reset'));
    self::assertSame(2, $this->revision());
    $this->connection->executeStatement('UPDATE users SET security_revision = security_revision + 1 WHERE id = ?', [self::USER]);
    $this->assertDenied('result_unavailable', fn () => $this->execute('confirm_password_reset', $command, $policy, $handler, 'reset'));
    self::assertSame(3, $this->revision());
  }

  public function testRegistrationReplaysCreatedAccountWithoutAnotherUserAndRejectsChangedPasswordProof(): void
  {
    $command = new RegisterUserCommand('registered-receipt@example.test', 'Registration-Password-789!', 'Receipt', 'Registrant');
    $handler = $this->service(RegisterUserHandler::class);
    $policy = $this->credentialPolicy();
    $first = $this->execute('register_user', $command, $policy, $handler, 'registration');
    self::assertInstanceOf(RegisterUserResult::class, $first);
    self::assertTrue($first->success);
    self::assertEquals($first, $this->execute('register_user', $command, $policy, $handler, 'registration'));
    self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM users WHERE email = ?', [$command->email]));
    $wrong = new RegisterUserCommand($command->email, 'Another-Password-123!', $command->firstName, $command->lastName);
    $this->assertDenied('result_unavailable', fn () => $this->execute('register_user', $wrong, $policy, $handler, 'registration'));
  }

  public function testUnknownResetMailboxRetainsNeutralDecoyAndCannotBecomeRealThroughReplay(): void
  {
    $command = new RequestPasswordResetCommand('absent-receipt@example.test');
    $handler = $this->service(RequestPasswordResetHandler::class);
    $policy = $this->credentialPolicy();
    $first = $this->execute('request_password_reset', $command, $policy, $handler, 'decoy');
    self::assertEquals($first, $this->execute('request_password_reset', $command, $policy, $handler, 'decoy'));
    self::assertSame(0, $this->connection->fetchOne('SELECT COUNT(*) FROM otps'));
    $this->connection->executeStatement('UPDATE users SET email = ?, security_revision = security_revision + 1 WHERE id = ?', [$command->email, self::USER]);
    $this->assertDenied('result_unavailable', fn () => $this->execute('request_password_reset', $command, $policy, $handler, 'decoy'));
    self::assertSame(0, $this->connection->fetchOne('SELECT COUNT(*) FROM otps'));
  }

  public function testFailedCurrentPasswordRequestReplaysWithoutAnotherCredentialAttempt(): void
  {
    $command = new RequestPasswordChangeCommand(self::USER, 'Incorrect-Password-123!');
    $handler = $this->service(RequestPasswordChangeHandler::class);
    $policy = $this->credentialPolicy();
    $first = $this->execute('request_password_change', $command, $policy, $handler, 'wrong-current-password');
    self::assertInstanceOf(RequestPasswordChangeResult::class, $first);
    self::assertFalse($first->success);
    self::assertEquals($first, $this->execute('request_password_change', $command, $policy, $handler, 'wrong-current-password'));
    self::assertSame(1, $this->connection->fetchOne('SELECT failed_login_attempts FROM users WHERE id = ?', [self::USER]));
    self::assertSame(0, $this->connection->fetchOne('SELECT COUNT(*) FROM otps'));
  }

  public function testInitialPasswordConfirmationReplaysAfterPasswordHasBeenConfigured(): void
  {
    $this->connection->executeStatement('UPDATE users SET password = NULL WHERE id = ?', [self::USER]);
    $this->manager->clear();
    $challenge = $this->challenge(OtpPurpose::SENSITIVE_OPERATION);
    $command = new ConfirmPasswordSetupCommand(self::USER, $challenge->challengeToken()->value, $challenge->code()->plain(), 'Initial-Password-321!');
    $handler = $this->service(ConfirmPasswordSetupHandler::class);
    $policy = $this->credentialPolicy();
    $first = $this->execute('confirm_initial_password', $command, $policy, $handler, 'initial-password');
    self::assertInstanceOf(ConfirmPasswordSetupResult::class, $first);
    self::assertTrue($first->success);
    $revision = $this->revision();
    self::assertEquals($first, $this->execute('confirm_initial_password', $command, $policy, $handler, 'initial-password'));
    self::assertSame($revision, $this->revision());
    self::assertTrue(new CredentialLifecycleProofAdapter($this->manager)->verifyPassword(self::USER, $command->password));
  }

  public function testRegistrationConfirmationReplaysExactSessionAndRevocationDeniesIt(): void
  {
    $this->connection->executeStatement("UPDATE users SET status = 'pending_verification', email_verified = FALSE WHERE id = ?", [self::USER]);
    $this->manager->clear();
    $challenge = $this->challenge(OtpPurpose::EMAIL_VERIFICATION);
    $command = new ConfirmRegistrationCommand($challenge->challengeToken()->value, $challenge->code()->plain());
    $handler = $this->service(ConfirmRegistrationHandler::class);
    $policy = $this->credentialPolicy();
    $first = $this->execute('confirm_registration', $command, $policy, $handler, 'registration-session');
    self::assertInstanceOf(ConfirmRegistrationResult::class, $first);
    self::assertTrue($first->success);
    $second = $this->execute('confirm_registration', $command, $policy, $handler, 'registration-session');
    self::assertInstanceOf(ConfirmRegistrationResult::class, $second);
    self::assertSame($first->accessToken, $second->accessToken);
    self::assertSame($first->refreshToken, $second->refreshToken);
    self::assertLessThanOrEqual($first->expiresIn, $second->expiresIn);
    self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM sessions WHERE user_id = ?', [self::USER]));
    $this->connection->executeStatement('UPDATE sessions SET revoked_at = NOW() WHERE user_id = ?', [self::USER]);
    $this->assertDenied('result_unavailable', fn () => $this->execute('confirm_registration', $command, $policy, $handler, 'registration-session'));
  }

  /**
   * @param string $change authorization fact changed after the original commit
   */
  #[DataProvider('registrationAuthorizationChanges')]
  #[WithEnvironmentVariable('ACCESS_TOKEN_INCLUDE_RBAC', 'true')]
  public function testRegistrationConfirmationDeniesChangedAuthorizationBeforeReplayingSecrets(string $change): void
  {
    $roleId = $this->connection->fetchOne('SELECT id FROM roles WHERE name = ?', ['admin']);
    self::assertIsString($roleId);
    $assignment = 'f00e8400-e29b-41d4-a716-4466554c0902';
    $this->connection->insert('role_assignments', ['id' => $assignment, 'role_id' => $roleId, 'subject_type' => 'user', 'subject_id' => self::USER, 'assigned_at' => '2026-10-11 12:00:00']);
    $this->connection->executeStatement("UPDATE users SET status = 'pending_verification', email_verified = FALSE WHERE id = ?", [self::USER]);
    $this->manager->clear();
    $challenge = $this->challenge(OtpPurpose::EMAIL_VERIFICATION);
    $command = new ConfirmRegistrationCommand($challenge->challengeToken()->value, $challenge->code()->plain());
    $handler = $this->service(ConfirmRegistrationHandler::class);
    $catalog = (object) ['revision' => 'before'];
    $currentGrants = $this->service(UserGrantSnapshotPort::class);
    $grants = $this->createStub(UserGrantSnapshotPort::class);
    $grants->method('current')->willReturnCallback(static function (string $userId, ?string $tenantId) use ($currentGrants, $catalog): UserGrantSnapshot {
      $snapshot = $currentGrants->current($userId, $tenantId);

      return new UserGrantSnapshot($snapshot->roles, $snapshot->permissions, $snapshot->catalogFacts + ['review_catalog_revision' => $catalog->revision]);
    });
    $authorization = new SessionAuthorizationProofAdapter($this->service(JwtTokenServicePort::class), $this->service(UserSecurityRevisionPort::class), $grants, new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('g', 32))));
    $policy = $this->credentialPolicy($authorization);
    $first = $this->execute('confirm_registration', $command, $policy, $handler, 'registration-grants');
    self::assertInstanceOf(ConfirmRegistrationResult::class, $first);
    self::assertTrue($first->success);
    self::assertNotNull($first->accessToken);
    self::assertNotNull($first->refreshToken);
    $claims = $this->service(JwtTokenServicePort::class)->decodePreAuthToken($first->accessToken);
    self::assertNotNull($claims);
    self::assertIsArray($claims['roles'] ?? null);
    self::assertContains('admin', $claims['roles']);
    self::assertIsArray($claims['permissions'] ?? null);
    self::assertNotEmpty($claims['permissions']);
    $second = $this->execute('confirm_registration', $command, $policy, $handler, 'registration-grants');
    self::assertInstanceOf(ConfirmRegistrationResult::class, $second);
    self::assertSame($first->accessToken, $second->accessToken);
    self::assertSame($first->refreshToken, $second->refreshToken);
    $pair = $this->service(JwtTokenServicePort::class)->decodeRefreshToken($first->refreshToken);
    self::assertNotNull($pair);
    $revision = $this->revision();
    if ('role' === $change) {
      self::assertSame(1, $this->connection->delete('role_assignments', ['id' => $assignment]));
    } elseif ('permission' === $change) {
      self::assertGreaterThan(0, $this->connection->delete('role_permissions', ['role_id' => $roleId]));
    } else {
      $catalog->revision = 'after';
    }
    self::assertSame($revision, $this->revision());
    self::assertNotNull($this->service(SessionReplayProofPort::class)->currentSessionId(self::USER, $pair['access_token_id'], $pair['refresh_token_id']));
    $this->assertDenied('result_unavailable', fn () => $this->execute('confirm_registration', $command, $policy, $handler, 'registration-grants'));
    self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM sessions WHERE user_id = ?', [self::USER]));
    self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
    self::assertSame(1, $this->connection->fetchOne('SELECT attempts FROM otps WHERE id = ?', [$challenge->id()->value]));
    self::assertSame($revision, $this->revision());
  }

  /**
   * @return iterable<string,array{string}> separately changed current authority facts
   */
  public static function registrationAuthorizationChanges(): iterable
  {
    yield 'role withdrawal' => ['role'];
    yield 'permission withdrawal' => ['permission'];
    yield 'active catalog change' => ['catalog'];
  }

  public function testMfaResendReplaysDaughterWithOriginalSignedPreauthenticationProof(): void
  {
    $challenge = $this->challenge(OtpPurpose::LOGIN);
    $this->connection->executeStatement("UPDATE otps SET created_at = NOW() - INTERVAL '2 minutes' WHERE id = ?", [$challenge->id()->value]);
    $this->manager->clear();
    $token = $this->service(JwtTokenServicePort::class)->generatePreAuthToken(self::USER, $challenge->challengeToken()->value, 'lifecycle@example.test', ['READ']);
    $command = new MfaResendCommand($token);
    $handler = $this->service(MfaResendHandler::class);
    $policy = $this->credentialPolicy();
    $first = $this->execute('mfa_resend', $command, $policy, $handler, 'mfa-resend');
    self::assertInstanceOf(MfaResendResult::class, $first);
    self::assertTrue($first->success);
    self::assertEquals($first, $this->execute('mfa_resend', $command, $policy, $handler, 'mfa-resend'));
    self::assertSame(2, $this->connection->fetchOne('SELECT COUNT(*) FROM otps'));
    self::assertNotSame($challenge->challengeToken()->value, $first->challengeToken);
  }

  public function testGenericChallengeCreationReplaysItsExactTokenWithoutReplacingIt(): void
  {
    $command = new GenerateOtpCommand(self::USER, \Otp\Application\Contract\Challenge\OtpPurpose::SENSITIVE_OPERATION, \Otp\Application\Contract\Challenge\OtpChannel::EMAIL, 'lifecycle@example.test');
    $handler = $this->service(GenerateOtpHandler::class);
    $policy = $this->challengePolicy();
    $first = $this->execute('createChallenge', $command, $policy, $handler, 'create-challenge');
    self::assertInstanceOf(GenerateOtpResult::class, $first);
    self::assertEquals($first, $this->execute('createChallenge', $command, $policy, $handler, 'create-challenge'));
    self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM otps'));
    $this->connection->executeStatement('UPDATE users SET security_revision = security_revision + 1 WHERE id = ?', [self::USER]);
    $this->assertDenied('result_unavailable', fn () => $this->execute('createChallenge', $command, $policy, $handler, 'create-challenge'));
    self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM otps'));
  }

  public function testGenericVerificationCommitsFailureOnceAndRejectsChangedCodeOnTheSameKey(): void
  {
    $challenge = $this->challenge(OtpPurpose::SENSITIVE_OPERATION);
    $wrong = '000000' === $challenge->code()->plain() ? '111111' : '000000';
    $command = new VerifyOtpCommand($wrong, challengeToken: $challenge->challengeToken()->value, expectedUserId: self::USER, expectedPurpose: OtpPurpose::SENSITIVE_OPERATION, expectedRecipient: 'lifecycle@example.test');
    $handler = $this->service(VerifyOtpHandler::class);
    $policy = $this->challengePolicy();
    $first = $this->execute('verifyChallenge', $command, $policy, $handler, 'verify-challenge');
    self::assertInstanceOf(VerifyOtpResult::class, $first);
    self::assertFalse($first->success);
    self::assertEquals($first, $this->execute('verifyChallenge', $command, $policy, $handler, 'verify-challenge'));
    self::assertSame(1, $this->connection->fetchOne('SELECT attempts FROM otps WHERE id = ?', [$challenge->id()->value]));
    $correct = new VerifyOtpCommand($challenge->code()->plain(), challengeToken: $challenge->challengeToken()->value, expectedUserId: self::USER, expectedPurpose: OtpPurpose::SENSITIVE_OPERATION, expectedRecipient: 'lifecycle@example.test');
    $this->assertDenied('input_mismatch', fn () => $this->execute('verifyChallenge', $correct, $policy, $handler, 'verify-challenge'));
    $success = $this->execute('verifyChallenge', $correct, $policy, $handler, 'correct-verification');
    self::assertInstanceOf(VerifyOtpResult::class, $success);
    self::assertTrue($success->success);
    self::assertEquals($success, $this->execute('verifyChallenge', $correct, $policy, $handler, 'correct-verification'));
    self::assertSame(2, $this->connection->fetchOne('SELECT attempts FROM otps WHERE id = ?', [$challenge->id()->value]));
  }

  public function testGenericResendReplaysIssuedDaughterWhenOriginalChallengeWasRevoked(): void
  {
    $challenge = $this->challenge(OtpPurpose::SENSITIVE_OPERATION);
    $this->connection->executeStatement("UPDATE otps SET created_at = NOW() - INTERVAL '2 minutes' WHERE id = ?", [$challenge->id()->value]);
    $this->manager->clear();
    $command = new ResendChallengeCommand($challenge->challengeToken()->value, self::USER);
    $handler = $this->service(ResendChallengeHandler::class);
    $policy = $this->challengePolicy();
    $first = $this->execute('resendChallenge', $command, $policy, $handler, 'resend-challenge');
    self::assertInstanceOf(ResendChallengeResult::class, $first);
    self::assertNotSame($challenge->challengeToken()->value, $first->token);
    self::assertEquals($first, $this->execute('resendChallenge', $command, $policy, $handler, 'resend-challenge'));
    self::assertSame(2, $this->connection->fetchOne('SELECT COUNT(*) FROM otps'));
  }

  public function testGenericChallengeProofRejectsAnotherOwnerBeforeCodeConsumption(): void
  {
    $challenge = $this->challenge(OtpPurpose::SENSITIVE_OPERATION);
    $this->connection->executeStatement('UPDATE otps SET user_id = ? WHERE id = ?', ['f00e8400-e29b-41d4-a716-4466554c0999', $challenge->id()->value]);
    $command = new VerifyOtpCommand($challenge->code()->plain(), challengeToken: $challenge->challengeToken()->value);
    $this->assertDenied('result_unavailable', fn () => $this->execute('verifyChallenge', $command, $this->challengePolicy(), $this->service(VerifyOtpHandler::class), 'other-owner'));
    self::assertSame(0, $this->connection->fetchOne('SELECT attempts FROM otps WHERE id = ?', [$challenge->id()->value]));
    self::assertSame(0, $this->connection->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
  }

  public function testUncertainProviderDeliveryRollsBackChallengeAndFencesAnotherAttempt(): void
  {
    $mailer = $this->createMock(\Symfony\Component\Mailer\MailerInterface::class);
    $mailer->expects(self::once())->method('send')->willThrowException(new RuntimeException('Provider acknowledgement lost.'));
    $notifier = new \Otp\Infrastructure\Adapter\Notifier\OtpNotifierAdapter($this->createStub(\Symfony\Component\Notifier\NotifierInterface::class), $mailer, new \Twig\Environment(new \Twig\Loader\ArrayLoader(['otp/email/code.html.twig' => '{{ code }}'])), idempotency: $this->execution);
    static::getContainer()->set(\Otp\Application\Port\Outbound\Internal\Challenge\OtpNotifierPort::class, $notifier);
    $command = new GenerateOtpCommand(self::USER, \Otp\Application\Contract\Challenge\OtpPurpose::SENSITIVE_OPERATION, \Otp\Application\Contract\Challenge\OtpChannel::EMAIL, 'lifecycle@example.test');
    $handler = $this->service(GenerateOtpHandler::class);
    $policy = $this->challengePolicy();
    $this->assertDenied('commit_unknown', fn () => $this->execute('createChallenge', $command, $policy, $handler, 'uncertain-delivery'));
    self::assertSame(0, $this->connection->fetchOne('SELECT COUNT(*) FROM otps'));
    self::assertSame('outcome_unknown', $this->connection->fetchOne('SELECT state FROM http_idempotency_receipts LIMIT 1'));
    $this->assertDenied('commit_unknown', fn () => $this->execute('createChallenge', $command, $policy, $handler, 'uncertain-delivery'));
    self::assertSame(0, $this->connection->fetchOne('SELECT COUNT(*) FROM otps'));
  }

  public function testCurrentNativeTenantChangeDeniesOldFactorReceiptDespiteUnchangedRequestActor(): void
  {
    $command = new SetupTotpCommand(self::USER, 'lifecycle@example.test');
    $handler = $this->service(SetupTotpHandler::class);
    $policy = $this->factorPolicy();
    $first = $this->execute('setupTotp', $command, $policy, $handler, 'tenant-setup');
    self::assertInstanceOf(SetupTotpResult::class, $first);
    $this->connection->executeStatement('UPDATE users SET tenant_id = ? WHERE id = ?', ['f00e8400-e29b-41d4-a716-4466554c0999', self::USER]);
    $this->assertDenied('result_unavailable', fn () => $this->execute('setupTotp', $command, $policy, $handler, 'tenant-setup'));
    self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM totp_enrollments'));
  }

  private function activeFactor(): TotpEnrollment
  {
    $factor = TotpEnrollment::startEnrollment(self::USER, new TotpSecret('JBSWY3DPEHPK3PXP'), 5);
    $factor->confirmPending(true);
    $this->service(TotpEnrollmentRepositoryPort::class)->save($factor);

    return $factor;
  }

  private function challenge(OtpPurpose $purpose): Otp
  {
    $challenge = Otp::generate(new OtpId('f00e8400-e29b-41d4-a716-4466554c0902'), self::USER, $purpose, OtpChannel::EMAIL, 'lifecycle@example.test');
    $this->service(OtpRepositoryPort::class)->save($challenge);

    return $challenge;
  }

  private function principal(): HttpIdempotencyPrincipalPort
  {
    $principal = $this->createStub(HttpIdempotencyPrincipalPort::class);
    $principal->method('current')->willReturn(new HttpIdempotencyPrincipal(self::USER));

    return $principal;
  }

  private function authority(): HttpIdempotencyPlatformAuthorityPort
  {
    $authority = $this->createStub(HttpIdempotencyPlatformAuthorityPort::class);
    $authority->method('permitsKey')->willReturn(true);

    return $authority;
  }

  private function factorPolicy(): AuthenticatorIdempotencyProofAdapter
  {
    return new AuthenticatorIdempotencyProofAdapter($this->manager, $this->service(UserSecurityRevisionPort::class), $this->principal(), $this->authority(), $this->service(HttpIdempotencyAccountPort::class));
  }

  private function credentialPolicy(?SessionAuthorizationProofPort $grants = null): CredentialLifecycleIdempotencyProofAdapter
  {
    return new CredentialLifecycleIdempotencyProofAdapter(new CredentialLifecycleProofAdapter($this->manager), new ChallengeLifecycleProofAdapter($this->manager), $this->principal(), $this->service(JwtTokenServicePort::class), $this->service(SessionReplayProofPort::class), $this->service(AuthenticationPolicyPort::class), $this->authority(), $this->service(HttpIdempotencyAccountPort::class), $grants ?? $this->service(SessionAuthorizationProofPort::class));
  }

  private function challengePolicy(): ChallengeIdempotencyProofAdapter
  {
    return new ChallengeIdempotencyProofAdapter(new ChallengeLifecycleProofAdapter($this->manager), $this->service(UserSecurityRevisionPort::class), $this->principal(), $this->authority(), $this->service(HttpIdempotencyAccountPort::class));
  }

  /**
   * @template T of CommandMessage
   *
   * @param string $name frozen owner operation
   * @param T $command original proof-bearing mutation
   * @param SensitiveIdempotencyProofPort $policy fresh owner authorization
   * @param callable(T):ResultMessage $handler real owning mutation handler
   * @param string $key logical request key
   */
  private function execute(string $name, CommandMessage $command, SensitiveIdempotencyProofPort $policy, callable $handler, string $key): ResultMessage
  {
    $this->execution->deactivate();
    $operation = null;
    foreach ([...AuthCredentialHttpIdempotencyOperationContributionAdapter::definitions(), ...OtpAuthenticatorHttpIdempotencyOperationContributionAdapter::definitions(), ...OtpChallengeHttpIdempotencyOperationContributionAdapter::definitions()] as $candidate) {
      if ($candidate->name === $name) {
        $operation = $candidate;

        break;
      }
    }
    self::assertNotNull($operation);
    $authorization = $policy->authorize($command, null);
    $scope = new IdempotencyScope($authorization->principalId, null, null, $name, '/api/auth/lifecycle-test');
    $stored = $this->engine->inspectProof($scope, $key);
    if (null !== $stored) {
      $authorization = $policy->authorize($command, $stored);
    }
    $begin = $this->engine->begin($scope, $key, $authorization->canonicalInput);
    $this->execution->activate($operation, $this->engine, $begin, $authorization->receiptProof, $policy, $command, $stored);

    return $this->execution->dispatchPrimary($command, static fn (): ResultMessage => $handler($command));
  }

  /**
   * @template T of object
   *
   * @param class-string<T> $class owning service
   *
   * @return T exact configured service
   */
  private function service(string $class): object
  {
    $service = static::getContainer()->get($class);
    self::assertInstanceOf($class, $service);

    return $service;
  }

  private function revision(): int
  {
    $revision = $this->connection->fetchOne('SELECT security_revision FROM users WHERE id = ?', [self::USER]);
    self::assertIsInt($revision);

    return $revision;
  }

  /**
   * @param string $reason expected denied reason
   * @param callable():mixed $operation attempted replay
   */
  private function assertDenied(string $reason, callable $operation): void
  {
    try {
      $operation();
      self::fail('A revoked proof must not disclose or mutate an application outcome.');
    } catch (IdempotencyDeniedException $exception) {
      self::assertSame($reason, $exception->reason);
    }
  }
}
