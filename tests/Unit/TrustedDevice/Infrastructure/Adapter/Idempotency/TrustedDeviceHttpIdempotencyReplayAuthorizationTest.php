<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Port\Outbound\Idempotency\OrdinaryHttpReplayAuthorizationPort;
use TrustedDevice\Infrastructure\Adapter\Idempotency\{TrustedDeviceHttpIdempotencyOperationContributionAdapter, TrustedDeviceHttpIdempotencyReplayAuthorizationAdapter};

/**
 * Class TrustedDeviceHttpIdempotencyReplayAuthorizationTest
 *
 * @category Test
 */
final class TrustedDeviceHttpIdempotencyReplayAuthorizationTest extends TestCase
{
  #[Test]
  public function currentForeignOwnerDeniesEvenWithOldSignedProof(): void
  {
    $database = $this->createStub(Connection::class);
    $database->method('fetchAssociative')->willReturn(['id' => 'target', 'user_id' => 'foreign', 'organization_id' => null]);
    $common = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $common->method('authorize')->willReturn(['principalId' => 'actor']);
    $this->expectException(IdempotencyDeniedException::class);
    new TrustedDeviceHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($this->request(), $this->proof('actor', 'target'));
  }

  #[Test]
  public function absentTargetWithoutOriginalOwnerFactsDenies(): void
  {
    $database = $this->createStub(Connection::class);
    $database->method('fetchAssociative')->willReturn(false);
    $common = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $common->method('authorize')->willReturn(['principalId' => 'actor']);
    $this->expectException(IdempotencyDeniedException::class);
    new TrustedDeviceHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($this->request(), $this->proof('foreign', 'target'));
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
    self::assertSame('actor', new TrustedDeviceHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($request, $proof)['deviceOwnerId']);
  }

  #[Test]
  public function liveOwnerProducesBoundOriginalFacts(): void
  {
    $database = $this->createMock(Connection::class);
    $database->expects(self::once())->method('fetchAssociative')->with('SELECT id, user_id FROM trusted_devices WHERE id = ?', ['target'])->willReturn(['id' => 'target', 'user_id' => 'actor', 'organization_id' => null]);
    $common = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $common->method('authorize')->willReturn(['principalId' => 'actor']);
    self::assertSame(['principalId' => 'actor', 'targetId' => 'target', 'deviceOwnerId' => 'actor'], new TrustedDeviceHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($this->request(), null));
  }

  #[Test]
  public function secretReplayRequiresTheExactStillActiveDevice(): void
  {
    $request = $this->trustRequest();
    $proof = new IdempotencyProof(['resourceId' => 'issued-device', 'resourceKind' => 'trusted_device'], new DateTimeImmutable('+1 minute'));
    $database = $this->createMock(Connection::class);
    $database->expects(self::once())->method('fetchAssociative')->with('SELECT id, user_id FROM trusted_devices WHERE id = ? AND revoked = FALSE AND expires_at > CURRENT_TIMESTAMP', ['issued-device'])->willReturn(['id' => 'issued-device', 'user_id' => 'actor']);
    $common = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $common->expects(self::once())->method('authorize')->with($request, $proof, null)->willReturn(['principalId' => 'actor']);
    self::assertSame('issued-device', new TrustedDeviceHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($request, $proof)['targetId']);
  }

  #[Test]
  public function revokedExpiredOrReplacedDeviceDeniesSecretReplay(): void
  {
    $database = $this->createStub(Connection::class);
    $database->method('fetchAssociative')->willReturn(false);
    $common = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $common->method('authorize')->willReturn(['principalId' => 'actor']);
    $this->expectException(IdempotencyDeniedException::class);
    new TrustedDeviceHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($this->trustRequest(), new IdempotencyProof(['resourceId' => 'issued-device', 'resourceKind' => 'trusted_device'], new DateTimeImmutable('+1 minute')));
  }

  private function trustRequest(): HttpIdempotencyAuthorizationRequest
  {
    foreach (TrustedDeviceHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ('trusted_device_trust' === $operation->name) {
        self::assertTrue($operation->secret);

        return new HttpIdempotencyAuthorizationRequest('actor', null, $operation, '/trusted-devices', [], []);
      }
    }
    self::fail('Trust operation is absent from the owner manifest.');
  }

  private function request(): HttpIdempotencyAuthorizationRequest
  {
    foreach (TrustedDeviceHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ('trusted_device_revoke' === $operation->name) {
        return new HttpIdempotencyAuthorizationRequest('actor', null, $operation, '/trusted_device/target', ['id' => 'target'], []);
      }
    }
    self::fail('Operation is absent from the owner manifest.');
  }

  private function proof(string $owner, string $target): IdempotencyProof
  {
    return new IdempotencyProof(['deviceOwnerId' => $owner, 'targetId' => $target], new DateTimeImmutable('+1 minute'));
  }
}
