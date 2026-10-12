<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\Service\Federation;

use Auth\Application\Contract\Federation\{FederatedConnection, FederatedFlow, FederatedLoginContext, FederatedProfile, ProviderId};
use Auth\Application\Contract\Token\{AuthenticationSession, SignInGrantType};
use Auth\Application\Port\Inbound\InteractiveSessionIssuerPort;
use Auth\Application\Port\Outbound\AuthenticationPolicyPort;
use Auth\Application\Port\Outbound\Federation\{FederatedFlowRepositoryPort, FederatedIdentityRepositoryPort, FederatedProviderClientPort};
use Auth\Application\Service\Federation\FederatedAuthenticationService;
use Auth\Domain\Exception\Federation\{FederatedAuthException, FederatedConflictException};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use User\Application\Contract\Federation\FederatedUser;
use User\Application\Port\Inbound\FederatedUserPort;

use function hash;

/**
 * Test FederatedVerificationTest
 *
 * Proves generic identities preserve explicit account linking and principal/issuer checks.
 *
 * @category Test
 */
#[CoversClass(FederatedAuthenticationService::class)]
final class FederatedVerificationTest extends TestCase
{
  #[Test]
  public function itIssuesTheStandardSessionForAnExistingGenericIdentity(): void
  {
    $identities = $this->createMock(FederatedIdentityRepositoryPort::class);
    $now = new DateTimeImmutable();
    $identities->method('findBySubject')->willReturn(new FederatedConnection('identity', 'local-user', ProviderId::from('acme'), 'subject', 'person@example.com', $now, $now, 'https://issuer.example'));
    $identities->expects(self::once())->method('save')->with(self::callback(static fn (FederatedConnection $connection): bool => 'https://issuer.example' === $connection->issuer));
    $users = $this->createMock(FederatedUserPort::class);
    $users->expects(self::never())->method('provision');
    $users->expects(self::once())->method('recordSuccessfulLogin')->with('local-user')->willReturn(new FederatedUser('local-user', 'person@example.com', true, false));
    $session = $this->createMock(InteractiveSessionIssuerPort::class);
    $session->expects(self::once())->method('issue')->with('local-user', 'person@example.com', null, null, null, false, SignInGrantType::OIDC)->willReturn(new AuthenticationSession(true, userId: 'local-user'));

    $login = $this->service($identities, $users, $session)->completeLogin(ProviderId::from('acme'), 'state', 'code', 'browser', null, new FederatedLoginContext(null, null, null));

    self::assertFalse($login->newAccount);
    self::assertSame('local-user', $login->login->userId);
    self::assertSame('/dashboard', $login->returnUrl);
    self::assertSame('oidc', SignInGrantType::OIDC->method());
  }

  #[Test]
  public function itNeverAutoLinksAnExistingAccountByEmail(): void
  {
    $identities = $this->createMock(FederatedIdentityRepositoryPort::class);
    $identities->expects(self::never())->method('save');
    $users = $this->createMock(FederatedUserPort::class);
    $users->expects(self::once())->method('provision')->with('person@example.com', 'Test', 'Person')->willReturn(null);
    $users->expects(self::never())->method('recordSuccessfulLogin');
    $session = $this->createMock(InteractiveSessionIssuerPort::class);
    $session->expects(self::never())->method('issue');
    $this->expectException(FederatedConflictException::class);

    $this->service($identities, $users, $session)->completeLogin(ProviderId::from('acme'), 'state', 'code', 'browser', null, new FederatedLoginContext(null, null, null));
  }

  #[Test]
  public function itHonorsDisabledFederatedAccountProvisioning(): void
  {
    $identities = $this->createMock(FederatedIdentityRepositoryPort::class);
    $identities->expects(self::never())->method('save');
    $users = $this->createMock(FederatedUserPort::class);
    $users->expects(self::never())->method('provision');
    $session = $this->createMock(InteractiveSessionIssuerPort::class);
    $session->expects(self::never())->method('issue');
    $this->expectException(FederatedAuthException::class);

    $this->service($identities, $users, $session, allowProvisioning: false)->completeLogin(ProviderId::from('acme'), 'state', 'code', 'browser', null, new FederatedLoginContext(null, null, null));
  }

  #[Test]
  public function itRejectsAnotherProviderIdentityBeforeAccessingAccounts(): void
  {
    $identities = $this->createMock(FederatedIdentityRepositoryPort::class);
    $identities->expects(self::never())->method('findBySubject');
    $users = $this->createMock(FederatedUserPort::class);
    $users->expects(self::never())->method('provision');
    $session = $this->createMock(InteractiveSessionIssuerPort::class);
    $session->expects(self::never())->method('issue');
    $profile = new FederatedProfile(ProviderId::from('another'), 'subject', 'person@example.com', true, 'Test', 'Person', 'https://issuer.example');
    $this->expectException(FederatedAuthException::class);

    $this->service($identities, $users, $session, profile: $profile)->completeLogin(ProviderId::from('acme'), 'state', 'code', 'browser', null, new FederatedLoginContext(null, null, null));
  }

  #[Test]
  public function itRequiresANewProviderIdentifierAfterAnIssuerChange(): void
  {
    $identities = $this->createMock(FederatedIdentityRepositoryPort::class);
    $identities->expects(self::once())->method('hasIssuerConflict')->with(ProviderId::from('acme'), 'https://issuer.example')->willReturn(true);
    $identities->expects(self::never())->method('findBySubject');
    $users = $this->createMock(FederatedUserPort::class);
    $users->expects(self::never())->method('provision');
    $session = $this->createMock(InteractiveSessionIssuerPort::class);
    $session->expects(self::never())->method('issue');
    $this->expectException(FederatedAuthException::class);

    $this->service($identities, $users, $session)->completeLogin(ProviderId::from('acme'), 'state', 'code', 'browser', null, new FederatedLoginContext(null, null, null));
  }

  #[Test]
  public function itRejectsLinkCompletionFromAnotherAuthenticatedPrincipal(): void
  {
    $provider = $this->createMock(FederatedProviderClientPort::class);
    $provider->method('isEnabled')->willReturn(true);
    $provider->expects(self::never())->method('complete');
    $identities = $this->createMock(FederatedIdentityRepositoryPort::class);
    $identities->expects(self::never())->method('save');
    $this->expectException(FederatedAuthException::class);

    $this->service($identities, $this->createStub(FederatedUserPort::class), $this->createStub(InteractiveSessionIssuerPort::class), provider: $provider, flow: $this->flow('link', 'another-user'))->completeLink(ProviderId::from('acme'), 'state', 'code', 'local-user', 'browser', null);
  }

  #[Test]
  public function itRejectsDisabledProviderCallbacksBeforeConsumingAFlow(): void
  {
    $provider = $this->createMock(FederatedProviderClientPort::class);
    $provider->method('isEnabled')->willReturn(false);
    $provider->expects(self::never())->method('complete');
    $flows = $this->createMock(FederatedFlowRepositoryPort::class);
    $flows->expects(self::never())->method('consume');
    $this->expectException(FederatedAuthException::class);

    $this->service($this->createStub(FederatedIdentityRepositoryPort::class), $this->createStub(FederatedUserPort::class), $this->createStub(InteractiveSessionIssuerPort::class), provider: $provider, flows: $flows)->completeLogin(ProviderId::from('acme'), 'state', 'code', 'browser', null, new FederatedLoginContext(null, null, null));
  }

  private function flow(string $intent = 'login', ?string $userId = null): FederatedFlow
  {
    return new FederatedFlow(hash('sha256', 'state'), hash('sha256', 'browser'), ProviderId::from('acme'), $intent, $userId, 'verifier', 'https://app.example/callback', '/dashboard', new DateTimeImmutable('+10 minutes'), hash('sha256', 'nonce'), proofExpiresAt: new DateTimeImmutable('+5 minutes'));
  }

  private function service(
    FederatedIdentityRepositoryPort $identities,
    FederatedUserPort $users,
    InteractiveSessionIssuerPort $session,
    bool $allowProvisioning = true,
    ?FederatedProfile $profile = null,
    ?FederatedProviderClientPort $provider = null,
    ?FederatedFlow $flow = null,
    ?FederatedFlowRepositoryPort $flows = null,
  ): FederatedAuthenticationService {
    if (null === $provider) {
      $provider = $this->createMock(FederatedProviderClientPort::class);
      $provider->method('isEnabled')->willReturn(true);
      $provider->expects(self::once())->method('complete')->with(ProviderId::from('acme'), 'https://app.example/callback', 'code', 'verifier', hash('sha256', 'nonce'))->willReturn($profile ?? new FederatedProfile(ProviderId::from('acme'), 'subject', 'person@example.com', true, 'Test', 'Person', 'https://issuer.example'));
    }
    if (null === $flows) {
      $flows = $this->createStub(FederatedFlowRepositoryPort::class);
      $flows->method('consume')->willReturn($flow ?? $this->flow());
      $flows->method('inspect')->willReturn($flow ?? $this->flow());
    }
    $transaction = $this->createStub(TransactionManagerPort::class);
    $transaction->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());
    $policy = $this->createStub(AuthenticationPolicyPort::class);
    $policy->method('federatedProvisioningEnabled')->willReturn($allowProvisioning);

    return new FederatedAuthenticationService($provider, $flows, $identities, $transaction, $users, $session, 'https://app.example', $policy);
  }
}
