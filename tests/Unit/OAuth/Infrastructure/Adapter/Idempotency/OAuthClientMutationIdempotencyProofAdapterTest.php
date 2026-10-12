<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use OAuth\Application\Contract\Client\ClientLifecycleProof;
use OAuth\Application\Port\Inbound\ClientLifecycleProofPort;
use OAuth\Application\UseCase\Command\Client\DeleteClient\DeleteClientCommand;
use OAuth\Application\UseCase\Command\Client\RegenerateClientSecret\{RegenerateClientSecretCommand, RegenerateClientSecretResult};
use OAuth\Infrastructure\Adapter\Idempotency\OAuthClientMutationIdempotencyProofAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\{HttpIdempotencyAuthorizationRequest, HttpIdempotencyPrincipal};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Message\VoidResult;
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyPrincipalPort, OrdinaryHttpReplayAuthorizationPort};

/**
 * Proves original-before fingerprint stability and fresh-after secret/revision denial before result opening.
 *
 * @category Adapter Tests
 */
final class OAuthClientMutationIdempotencyProofAdapterTest extends TestCase
{
  private const string CLIENT = 'aa0e8400-e29b-41d4-a716-4466554c0a01';

  #[Test]
  public function secretRotationReplayUsesSignedBeforeRevisionAndChecksTheCurrentAfterLineage(): void
  {
    $current = new ClientLifecycleProof(self::CLIENT, 1, true, false, 'old-secret-fingerprint');
    $clients = $this->createStub(ClientLifecycleProofPort::class);
    $clients->method('current')->willReturnCallback(static function () use (&$current): ClientLifecycleProof { return $current; });
    $clients->method('matchesSecret')->willReturn(true);
    $policy = $this->policy($clients);
    $command = new RegenerateClientSecretCommand(self::CLIENT);
    $before = $policy->authorize($command, null);
    $current = new ClientLifecycleProof(self::CLIENT, 2, true, false, 'new-secret-fingerprint');
    $proof = new IdempotencyProof([...$before->receiptProof, ...$policy->proofForResult($command, new RegenerateClientSecretResult(self::CLIENT, 'new-secret'))], new DateTimeImmutable('2030-01-01'));
    $replay = $policy->authorize($command, $proof);

    self::assertSame($before->canonicalInput, $replay->canonicalInput);
    self::assertSame('1', $replay->receiptProof['client_before_revision']);
    self::assertSame('2', $replay->receiptProof['client_after_revision']);
    self::assertSame('new-secret-fingerprint', $replay->receiptProof['client_after_secret']);
    self::assertSame('oauth_client', $replay->receiptProof['resourceKind']);
  }

  #[Test]
  public function aLaterSecretRotationDeniesBeforeTheCiphertextCanBeDecoded(): void
  {
    $current = new ClientLifecycleProof(self::CLIENT, 3, true, false, 'later-secret');
    $clients = $this->createStub(ClientLifecycleProofPort::class);
    $clients->method('current')->willReturn($current);
    $clients->method('matchesSecret')->willReturn(true);
    $this->expectException(IdempotencyDeniedException::class);

    $this->policy($clients)->authorize(new RegenerateClientSecretCommand(self::CLIENT), $this->proof());
  }

  #[Test]
  public function changedStoredSecretDeniesEvenWhenTheRevisionWasNotAdvanced(): void
  {
    $clients = $this->createStub(ClientLifecycleProofPort::class);
    $clients->method('current')->willReturn(new ClientLifecycleProof(self::CLIENT, 2, true, false, 'different-secret'));
    $this->expectException(IdempotencyDeniedException::class);

    $this->policy($clients)->authorize(new RegenerateClientSecretCommand(self::CLIENT), $this->proof());
  }

  #[Test]
  public function aRemovedClientNeverOpensAnOldSecretResult(): void
  {
    $clients = $this->createStub(ClientLifecycleProofPort::class);
    $clients->method('current')->willReturn(null);
    $this->expectException(IdempotencyDeniedException::class);

    $this->policy($clients)->authorize(new RegenerateClientSecretCommand(self::CLIENT), $this->proof());
  }

  #[Test]
  public function revokedActorAuthorityIsCheckedBeforeEvenLookingUpClientLineage(): void
  {
    $clients = $this->createMock(ClientLifecycleProofPort::class);
    $clients->expects(self::never())->method('current');
    $authorizer = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $authorizer->expects(self::once())->method('authorize')->willThrowException(new IdempotencyDeniedException('authorization_unavailable'));
    $principal = $this->createStub(HttpIdempotencyPrincipalPort::class);
    $principal->method('current')->willReturn(new HttpIdempotencyPrincipal('actor', 'tenant'));
    $this->expectException(IdempotencyDeniedException::class);

    new OAuthClientMutationIdempotencyProofAdapter($clients, $principal, $authorizer)->authorize(new RegenerateClientSecretCommand(self::CLIENT), $this->proof());
  }

  #[Test]
  public function resultCaptureRefusesAPlainSecretThatDoesNotMatchTheCommittedClient(): void
  {
    $clients = $this->createStub(ClientLifecycleProofPort::class);
    $clients->method('current')->willReturn(new ClientLifecycleProof(self::CLIENT, 2, true, false, 'new-secret-fingerprint'));
    $clients->method('matchesSecret')->willReturn(false);
    $this->expectException(IdempotencyDeniedException::class);

    $this->policy($clients)->proofForResult(new RegenerateClientSecretCommand(self::CLIENT), new RegenerateClientSecretResult(self::CLIENT, 'wrong-secret'));
  }

  #[Test]
  public function deletedClientCanReachItsSavedDeleteReceiptWithoutReopeningAnySecret(): void
  {
    $current = new ClientLifecycleProof(self::CLIENT, 1, true, false, 'secret-fingerprint');
    $clients = $this->createStub(ClientLifecycleProofPort::class);
    $clients->method('current')->willReturnCallback(static function () use (&$current): ClientLifecycleProof { return $current; });
    $policy = $this->policy($clients, 'clients.delete', '');
    $command = new DeleteClientCommand(self::CLIENT);
    $first = $policy->authorize($command, null);
    $current = new ClientLifecycleProof(self::CLIENT, 2, false, true, 'secret-fingerprint');
    $proof = new IdempotencyProof([...$first->receiptProof, ...$policy->proofForResult($command, new VoidResult())], new DateTimeImmutable('2030-01-01'));

    self::assertSame('2', $policy->authorize($command, null)->receiptProof['client_before_revision']);
    self::assertSame($first->canonicalInput, $policy->authorize($command, $proof)->canonicalInput);
    self::assertSame('1', $proof->metadata['client_after_deleted']);
  }

  private function policy(ClientLifecycleProofPort $clients, string $permission = 'clients.update', string $suffix = '/regenerate-secret'): OAuthClientMutationIdempotencyProofAdapter
  {
    $principal = $this->createStub(HttpIdempotencyPrincipalPort::class);
    $principal->method('current')->willReturn(new HttpIdempotencyPrincipal('actor', 'tenant'));
    $authorizer = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $authorizer->method('authorize')->willReturnCallback(static function (HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $stored) use ($permission, $suffix): array {
      self::assertSame("is_granted('" . $permission . "')", $request->platformSecurityExpression);
      self::assertSame('/api/clients/' . self::CLIENT . $suffix, $request->resourceUri);
      self::assertSame('actor', $request->principalId);
      self::assertSame('tenant', $request->tenantId);

      return null !== $stored ? $stored->metadata : ['principalId' => 'actor', 'tenantId' => 'tenant'];
    });

    return new OAuthClientMutationIdempotencyProofAdapter($clients, $principal, $authorizer);
  }

  private function proof(): IdempotencyProof
  {
    return new IdempotencyProof([
      'principalId' => 'actor',
      'tenantId' => 'tenant',
      'resourceId' => self::CLIENT,
      'resourceKind' => 'oauth_client',
      'client_before_revision' => '1',
      'client_after_revision' => '2',
      'client_after_secret' => 'new-secret-fingerprint',
      'client_after_active' => '1',
      'client_after_deleted' => '0',
    ], new DateTimeImmutable('2030-01-01'));
  }
}
