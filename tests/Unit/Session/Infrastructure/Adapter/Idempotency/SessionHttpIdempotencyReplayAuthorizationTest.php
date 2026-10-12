<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Session\Infrastructure\Adapter\Idempotency\{SessionHttpIdempotencyOperationContributionAdapter, SessionHttpIdempotencyReplayAuthorizationAdapter};
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Port\Outbound\Idempotency\OrdinaryHttpReplayAuthorizationPort;

/**
 * Class SessionHttpIdempotencyReplayAuthorizationTest
 *
 * @category Test
 */
final class SessionHttpIdempotencyReplayAuthorizationTest extends TestCase
{
  #[Test]
  public function currentForeignOwnerDeniesEvenWithOldSignedProof(): void
  {
    $database = $this->createStub(Connection::class);
    $database->method('fetchAssociative')->willReturn(['id' => 'target', 'user_id' => 'foreign', 'organization_id' => null]);
    $common = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $common->method('authorize')->willReturn(['principalId' => 'actor']);
    $this->expectException(IdempotencyDeniedException::class);
    new SessionHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($this->request(), $this->proof('actor', 'target'));
  }

  #[Test]
  public function absentTargetWithoutOriginalOwnerFactsDenies(): void
  {
    $database = $this->createStub(Connection::class);
    $database->method('fetchAssociative')->willReturn(false);
    $common = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $common->method('authorize')->willReturn(['principalId' => 'actor']);
    $this->expectException(IdempotencyDeniedException::class);
    new SessionHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($this->request(), $this->proof('foreign', 'target'));
  }

  #[Test]
  public function signedRemovedTargetRequiresCurrentCommonAuthorization(): void
  {
    $request = $this->request();
    $proof = $this->proof('actor', 'target');
    $database = $this->createStub(Connection::class);
    $database->method('fetchAssociative')->willReturn(false);
    $common = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $common->expects(self::once())->method('authorize')->with($request, $proof, null)->willReturn(['principalId' => 'actor']);
    self::assertSame('actor', new SessionHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($request, $proof)['sessionOwnerId']);
  }

  #[Test]
  public function liveOwnerProducesBoundOriginalFacts(): void
  {
    $database = $this->createMock(Connection::class);
    $database->expects(self::once())->method('fetchAssociative')->with('SELECT id, user_id FROM sessions WHERE id = ?', ['target'])->willReturn(['id' => 'target', 'user_id' => 'actor', 'organization_id' => null]);
    $common = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $common->method('authorize')->willReturn(['principalId' => 'actor']);
    self::assertSame(['principalId' => 'actor', 'targetId' => 'target', 'sessionOwnerId' => 'actor'], new SessionHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($this->request(), null));
  }

  private function request(): HttpIdempotencyAuthorizationRequest
  {
    foreach (SessionHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ('session_revoke' === $operation->name) {
        return new HttpIdempotencyAuthorizationRequest('actor', null, $operation, '/session/target', ['id' => 'target'], []);
      }
    }
    self::fail('Operation is absent from the owner manifest.');
  }

  private function proof(string $owner, string $target): IdempotencyProof
  {
    return new IdempotencyProof(['sessionOwnerId' => $owner, 'targetId' => $target], new DateTimeImmutable('+1 minute'));
  }
}
