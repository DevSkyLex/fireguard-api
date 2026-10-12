<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Contract\Federation\{FederatedStartFlowProof, ProviderId};
use Auth\Application\Port\Outbound\Federation\{FederatedProviderClientPort, FederatedStartFlowProofPort};
use Auth\Application\UseCase\Command\Federation\StartFederation\{StartFederationCommand, StartFederationResult};
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Auth\Infrastructure\Adapter\Federation\SignedFederatedBrowserBindingAdapter;
use Auth\Infrastructure\Adapter\Idempotency\FederatedStartIdempotencyProofAdapter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Idempotency\IdempotencyProof;
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Infrastructure\Idempotency\HmacIdempotencyFingerprintAdapter;
use User\Application\Contract\User\UserSecurityProof;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function base64_encode;
use function hash;
use function str_repeat;

/**
 * Test FederatedStartIdempotencyProofAdapterTest
 *
 * @category Test
 */
final class FederatedStartIdempotencyProofAdapterTest extends TestCase
{
  #[Test]
  public function initialAndRetryKeepOneBrowserExecutionIdentityAndTheOriginalUrl(): void
  {
    [$adapter, $command, $result, $flows] = $this->fixture();
    $flows->expects(self::exactly(2))->method('find')->with(hash('sha256', 'state-once'), $command->browserBinding, $command->provider, 'login')->willReturn($this->flow($command));
    $first = $adapter->authorize($command, null);
    self::assertArrayNotHasKey('naturalKeyAllowed', $first->receiptProof);
    $proof = new IdempotencyProof($adapter->proofForResult($command, $result), new DateTimeImmutable('2026-10-12T00:05:00+00:00'));
    $retry = $adapter->authorize($command, $proof);
    self::assertSame($first->principalId, $retry->principalId);
    self::assertSame($first->canonicalInput, $retry->canonicalInput);
    self::assertSame($result, $adapter->replayResult($result, $proof));
    self::assertSame('https://issuer.example/authorize?state=state-once&nonce=nonce-once&code_challenge=challenge-once', $result->authorizationUrl);
  }

  #[Test]
  public function anotherAuthenticatedBrowserTicketCannotReplayTheFirstStart(): void
  {
    [$adapter, $command, $result, $flows, $bindings] = $this->fixture();
    $flows->expects(self::once())->method('find')->willReturn($this->flow($command));
    $proof = new IdempotencyProof($adapter->proofForResult($command, $result), new DateTimeImmutable('2026-10-12T00:05:00+00:00'));
    $otherTicket = $bindings->create();
    $otherBinding = $bindings->verify($otherTicket);
    self::assertNotNull($otherBinding);
    $this->expectException(FederatedAuthException::class);
    $adapter->authorize(new StartFederationCommand($command->provider, $otherTicket, '/account', browserBindingExpiresAt: $otherBinding->expiresAt), $proof);
  }

  #[Test]
  public function clientKeyMaterialCannotSubstituteBrowserPossession(): void
  {
    [$adapter, $command, , $flows] = $this->fixture();
    $flows->expects(self::never())->method('find');
    $this->expectException(FederatedAuthException::class);
    $adapter->authorize(new StartFederationCommand($command->provider, 'client-key', browserBindingExpiresAt: $command->browserBindingExpiresAt), null);
  }

  #[Test]
  public function consumedExpiredOrExchangingFlowDeniesBeforeResultDecode(): void
  {
    [$adapter, $command, $result, $flows] = $this->fixture();
    $flows->expects(self::exactly(2))->method('find')->willReturnOnConsecutiveCalls($this->flow($command), null);
    $proof = new IdempotencyProof($adapter->proofForResult($command, $result), new DateTimeImmutable('2026-10-12T00:05:00+00:00'));
    $this->expectException(FederatedAuthException::class);
    $adapter->authorize($command, $proof);
  }

  #[Test]
  public function linkReplayRequiresTheSameCurrentAccountRevision(): void
  {
    [$adapter, $command, $result, $flows, , $users] = $this->fixture('user');
    $flows->expects(self::once())->method('find')->willReturn($this->flow($command));
    $users->expects(self::exactly(2))->method('current')->with('user')->willReturnOnConsecutiveCalls(new UserSecurityProof('user', 4, true, true), new UserSecurityProof('user', 5, true, true));
    $proof = new IdempotencyProof($adapter->proofForResult($command, $result), new DateTimeImmutable('2026-10-12T00:05:00+00:00'));
    $this->expectException(FederatedAuthException::class);
    $adapter->authorize($command, $proof);
  }

  #[Test]
  public function linkExecutionPublishesTheVerifiedAccountForReceiptLifecycle(): void
  {
    [$adapter, $command, , $flows, , $users] = $this->fixture('user');
    $flows->expects(self::never())->method('find');
    $users->expects(self::once())->method('current')->with('user')->willReturn(new UserSecurityProof('user', 4, true, true));
    $authorization = $adapter->authorize($command, null);
    self::assertSame('user', $authorization->principalId);
    self::assertSame(hash('sha256', $command->browserBinding), $authorization->receiptProof['browser_binding_hash']);
  }

  #[Test]
  public function signedFlowCannotBeUsedToLinkAnotherAccount(): void
  {
    [$adapter, $command, $result, $flows, , $users] = $this->fixture('user');
    $users->expects(self::exactly(2))->method('current')->willReturnCallback(static fn (string $user): UserSecurityProof => new UserSecurityProof($user, 4, true, true));
    $flows->expects(self::once())->method('find')->willReturn($this->flow($command));
    $proof = new IdempotencyProof($adapter->proofForResult($command, $result), new DateTimeImmutable('2026-10-12T00:05:00+00:00'));
    $this->expectException(FederatedAuthException::class);
    $adapter->authorize(new StartFederationCommand($command->provider, $command->browserBinding, userId: 'other-user', browserBindingExpiresAt: $command->browserBindingExpiresAt), $proof);
  }

  #[Test]
  public function disabledProviderRejectsEvenAValidServerTicket(): void
  {
    [$adapter, $command, , $flows] = $this->fixture(enabled: false);
    $flows->expects(self::never())->method('find');
    $this->expectException(FederatedAuthException::class);
    $this->expectExceptionMessage('This sign-in provider is unavailable.');
    $adapter->authorize($command, null);
  }

  #[Test]
  public function anExpiredReceiptStillRequiresTheOriginalNativeProof(): void
  {
    [$adapter, $command, , $flows] = $this->fixture();
    $flows->expects(self::never())->method('find');
    $this->expectException(FederatedAuthException::class);
    $adapter->authorize($command, new IdempotencyProof([], new DateTimeImmutable('2026-10-12T00:00:00+00:00')));
  }

  #[Test]
  public function validNativeProofIsRecheckedBeforeTheEngineEvaluatesReceiptExpiry(): void
  {
    [$adapter, $command, $result, $flows] = $this->fixture();
    $flows->expects(self::exactly(2))->method('find')->willReturn($this->flow($command));
    $proof = new IdempotencyProof($adapter->proofForResult($command, $result), new DateTimeImmutable('2026-10-11T23:59:59+00:00'));
    $authorization = $adapter->authorize($command, $proof);
    self::assertSame(hash('sha256', 'state-once'), $authorization->receiptProof['state_hash']);
    self::assertSame($adapter->authorize($command, null)->canonicalInput, $authorization->canonicalInput);
  }

  /**
   * @return array{FederatedStartIdempotencyProofAdapter, StartFederationCommand, StartFederationResult, FederatedStartFlowProofPort&\PHPUnit\Framework\MockObject\MockObject, SignedFederatedBrowserBindingAdapter, UserSecurityRevisionPort&\PHPUnit\Framework\MockObject\MockObject}
   */
  private function fixture(?string $userId = null, bool $enabled = true): array
  {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-12T00:00:00+00:00'));
    $bindings = new SignedFederatedBrowserBindingAdapter(new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('b', 32))), $clock);
    $ticket = $bindings->create();
    $binding = $bindings->verify($ticket);
    self::assertNotNull($binding);
    $command = new StartFederationCommand(ProviderId::from('acme'), $ticket, '/account', $userId, $binding->expiresAt);
    $result = new StartFederationResult('https://issuer.example/authorize?state=state-once&nonce=nonce-once&code_challenge=challenge-once', $binding->expiresAt);
    $providers = $this->createMock(FederatedProviderClientPort::class);
    $providers->method('isEnabled')->willReturn($enabled);
    $providers->expects(self::never())->method('start');
    $providers->expects(self::never())->method('complete');
    $flows = $this->createMock(FederatedStartFlowProofPort::class);
    $users = $this->createMock(UserSecurityRevisionPort::class);
    if (null === $userId) {
      $users->expects(self::never())->method('current');
    }

    return [new FederatedStartIdempotencyProofAdapter($bindings, $providers, $flows, $users, $clock), $command, $result, $flows, $bindings, $users];
  }

  private function flow(StartFederationCommand $command): FederatedStartFlowProof
  {
    return new FederatedStartFlowProof(hash('sha256', 'state-once'), hash('sha256', $command->browserBinding), $command->provider, null === $command->userId ? 'login' : 'link', $command->userId, new DateTimeImmutable('2026-10-12T00:10:00+00:00'));
  }
}
