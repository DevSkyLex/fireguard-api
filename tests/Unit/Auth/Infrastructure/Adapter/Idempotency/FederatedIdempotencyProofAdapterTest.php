<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Contract\Federation\{FederatedFlow, FederatedLogin, FederatedLoginContext, ProviderId};
use Auth\Application\Contract\Token\AuthenticationSession;
use Auth\Application\Port\Outbound\Federation\{FederatedFlowRepositoryPort, FederatedProviderClientPort};
use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionAuthorizationProofPort};
use Auth\Application\UseCase\Command\Federation\CompleteFederation\{CompleteFederationCommand, CompleteFederationResult};
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Auth\Infrastructure\Adapter\Idempotency\FederatedIdempotencyProofAdapter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Inbound\SessionReplayProofPort;
use Shared\Application\Contract\Idempotency\IdempotencyProof;
use User\Application\Contract\User\UserSecurityProof;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function array_replace;

/**
 * Class FederatedIdempotencyProofAdapterTest
 *
 * Proves that callback replay retains browser, account, authorization and exact session guards.
 *
 * @category Test
 */
final class FederatedIdempotencyProofAdapterTest extends TestCase
{
  // #region Methods
  /**
   * Method testCompletedReplayRetainsVerifiedExecutionIdentity
   *
   * @access public
   *
   * @return void
   */
  public function testCompletedReplayRetainsVerifiedExecutionIdentity(): void
  {
    [$adapter, $command, $users, $sessions, $grants, $tokens] = $this->fixture();
    $tokens->expects(self::never())->method('decodeRefreshToken');
    $users->expects(self::once())->method('current')->with('user')->willReturn(new UserSecurityProof('user', 4, true, true));
    $grants->expects(self::once())->method('currentFingerprint')->with('user')->willReturn('current-grants');
    $sessions->expects(self::once())->method('currentSessionId')->with('user', 'access', 'refresh')->willReturn('session');
    $authorization = $adapter->authorize($command, $this->proof());
    self::assertSame('federation:state-hash', $authorization->principalId);
    self::assertIsArray($authorization->canonicalInput);
    self::assertSame('state', $authorization->canonicalInput['state']);
    self::assertSame('browser', $authorization->canonicalInput['browser_binding']);
    self::assertSame('code', $authorization->canonicalInput['code']);
    self::assertSame('state-hash:browser-hash:oidc:login', $authorization->receiptProof['naturalKeyIdentity']);
  }

  /**
   * Method testChangedReceiptFlowFactsDenyBeforeAccountLookup
   *
   * @access public
   *
   * @param array<string, string> $changedFacts authenticated receipt fact changed after issuance
   *
   * @return void
   */
  #[DataProvider('changedFlowFacts')]
  public function testChangedReceiptFlowFactsDenyBeforeAccountLookup(array $changedFacts): void
  {
    [$adapter, $command, $users, $sessions, $grants, $tokens] = $this->fixture();
    $tokens->expects(self::never())->method('decodeRefreshToken');
    $users->expects(self::never())->method('current');
    $sessions->expects(self::never())->method('currentSessionId');
    $grants->expects(self::never())->method('currentFingerprint');
    $this->expectException(FederatedAuthException::class);
    $this->expectExceptionMessage('This connection request is no longer valid.');
    $adapter->authorize($command, $this->proof($changedFacts));
  }

  /**
   * Method changedFlowFacts
   *
   * @access public
   *
   * @return iterable<string, array{array<string, string>}> independently bound callback facts
   */
  public static function changedFlowFacts(): iterable
  {
    yield 'state' => [['state_hash' => 'other-state']];
    yield 'browser' => [['browser_binding_hash' => 'other-browser']];
    yield 'provider' => [['provider' => 'other-provider']];
    yield 'intent' => [['intent' => 'link']];
    yield 'owner' => [['user_id' => 'other-user']];
  }

  /**
   * Method testChangedSecurityRevisionDeniesBeforeAuthorizationAndSessionLookup
   *
   * @access public
   *
   * @return void
   */
  public function testChangedSecurityRevisionDeniesBeforeAuthorizationAndSessionLookup(): void
  {
    [$adapter, $command, $users, $sessions, $grants, $tokens] = $this->fixture();
    $tokens->expects(self::never())->method('decodeRefreshToken');
    $users->expects(self::once())->method('current')->with('user')->willReturn(new UserSecurityProof('user', 5, true, true));
    $grants->expects(self::never())->method('currentFingerprint');
    $sessions->expects(self::never())->method('currentSessionId');
    $this->expectException(FederatedAuthException::class);
    $this->expectExceptionMessage('This account cannot sign in.');
    $adapter->authorize($command, $this->proof());
  }

  /**
   * Method testRemovedAuthorizationDeniesBeforeSessionLookup
   *
   * @access public
   *
   * @return void
   */
  public function testRemovedAuthorizationDeniesBeforeSessionLookup(): void
  {
    [$adapter, $command, $users, $sessions, $grants, $tokens] = $this->fixture();
    $tokens->expects(self::never())->method('decodeRefreshToken');
    $users->expects(self::once())->method('current')->with('user')->willReturn(new UserSecurityProof('user', 4, true, true));
    $grants->expects(self::once())->method('currentFingerprint')->with('user')->willReturn('removed-grants');
    $sessions->expects(self::never())->method('currentSessionId');
    $this->expectException(FederatedAuthException::class);
    $this->expectExceptionMessage('This account cannot sign in.');
    $adapter->authorize($command, $this->proof());
  }

  /**
   * Method testRevokedIssuedSessionDeniesReplay
   *
   * @access public
   *
   * @return void
   */
  public function testRevokedIssuedSessionDeniesReplay(): void
  {
    [$adapter, $command, $users, $sessions, $grants, $tokens] = $this->fixture();
    $tokens->expects(self::never())->method('decodeRefreshToken');
    $users->expects(self::once())->method('current')->with('user')->willReturn(new UserSecurityProof('user', 4, true, true));
    $grants->expects(self::once())->method('currentFingerprint')->with('user')->willReturn('current-grants');
    $sessions->expects(self::once())->method('currentSessionId')->with('user', 'access', 'refresh')->willReturn(null);
    $this->expectException(FederatedAuthException::class);
    $this->expectExceptionMessage('This account cannot sign in.');
    $adapter->authorize($command, $this->proof());
  }

  /**
   * Method testIssuedProofAndReplayPreserveOriginalPairAndAbsoluteExpiry
   *
   * @access public
   *
   * @return void
   */
  public function testIssuedProofAndReplayPreserveOriginalPairAndAbsoluteExpiry(): void
  {
    [$adapter, $command, $users, $sessions, $grants, $tokens] = $this->fixture();
    $users->expects(self::once())->method('current')->with('user')->willReturn(new UserSecurityProof('user', 4, true, true));
    $grants->expects(self::once())->method('issuedFingerprint')->with('user', 'access-token')->willReturn('current-grants');
    $tokens->expects(self::once())->method('decodeRefreshToken')->with('refresh-token')->willReturn(['user_id' => 'user', 'access_token_id' => 'access', 'refresh_token_id' => 'refresh', 'expires_at' => 2000000000, 'scopes' => ['READ']]);
    $sessions->expects(self::once())->method('currentSessionId')->with('user', 'access', 'refresh')->willReturn('session');
    $result = new CompleteFederationResult(new FederatedLogin(new AuthenticationSession(true, 'user', 'owner@example.test', 'access-token', 'refresh-token', expiresIn: 60, scopes: ['READ']), '/account', false));
    $proof = $adapter->proofForResult($command, $result);
    self::assertSame('4', $proof['security_revision']);
    self::assertSame('session', $proof['session_id']);
    self::assertSame('access', $proof['access_token_id']);
    self::assertSame('refresh', $proof['refresh_token_id']);
    self::assertSame('2000000000', $proof['refresh_expires_at']);
    $replayed = $adapter->replayResult($result, new IdempotencyProof(array_replace($proof, ['access_expires_at' => '1']), new DateTimeImmutable('+5 minutes')));
    self::assertInstanceOf(CompleteFederationResult::class, $replayed);
    self::assertNotNull($replayed->login);
    self::assertSame('access-token', $replayed->login->login->accessToken);
    self::assertSame('refresh-token', $replayed->login->login->refreshToken);
    self::assertSame(0, $replayed->login->login->expiresIn);
    self::assertSame('/account', $replayed->login->returnUrl);
  }

  /**
   * Method fixture
   *
   * @access private
   *
   * @return array{FederatedIdempotencyProofAdapter,CompleteFederationCommand,UserSecurityRevisionPort&MockObject,SessionReplayProofPort&MockObject,SessionAuthorizationProofPort&MockObject,JwtTokenServicePort&MockObject} verified completed callback and fresh owner ports
   */
  private function fixture(): array
  {
    $provider = ProviderId::from('oidc');
    $command = new CompleteFederationCommand($provider, 'state', 'code', 'browser', null, new FederatedLoginContext(null, null, null));
    $flows = $this->createMock(FederatedFlowRepositoryPort::class);
    $flows->method('inspect')->with('state', 'browser', $provider, 'login')->willReturn(new FederatedFlow('state-hash', 'browser-hash', $provider, 'login', null, '', '', '/account', new DateTimeImmutable('+5 minutes'), stage: 'completed', completedUserId: 'user'));
    $providers = $this->createMock(FederatedProviderClientPort::class);
    $providers->method('isEnabled')->with($provider)->willReturn(true);
    $users = $this->createMock(UserSecurityRevisionPort::class);
    $sessions = $this->createMock(SessionReplayProofPort::class);
    $grants = $this->createMock(SessionAuthorizationProofPort::class);
    $tokens = $this->createMock(JwtTokenServicePort::class);

    return [new FederatedIdempotencyProofAdapter($flows, $providers, $users, $tokens, $sessions, $grants), $command, $users, $sessions, $grants, $tokens];
  }

  /**
   * Method proof
   *
   * @access private
   *
   * @param array<string, string> $overrides independently changed receipt facts
   *
   * @return IdempotencyProof original completed browser and session envelope
   */
  private function proof(array $overrides = []): IdempotencyProof
  {
    return new IdempotencyProof(array_replace(['state_hash' => 'state-hash', 'browser_binding_hash' => 'browser-hash', 'provider' => 'oidc', 'intent' => 'login', 'user_id' => 'user', 'security_revision' => '4', 'authorization_fingerprint' => 'current-grants', 'session_id' => 'session', 'access_token_id' => 'access', 'refresh_token_id' => 'refresh'], $overrides), new DateTimeImmutable('+5 minutes'));
  }
  // #endregion
}
