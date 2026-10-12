<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Notification\Application\UseCase\Command\Notification\UpdateNotificationPreferences\UpdateNotificationPreferencesResult;
use Notification\Application\UseCase\Query\Notification\GetNotificationPreferences\NotificationPreferenceResult;
use Notification\Infrastructure\Adapter\Idempotency\{NotificationHttpIdempotencyOperationContributionAdapter, NotificationHttpIdempotencyReplayAuthorizationAdapter};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Port\Outbound\Idempotency\OrdinaryHttpReplayAuthorizationPort;
use Shared\Infrastructure\Idempotency\HttpIdempotencyResultCodec;

/**
 * Class NotificationHttpIdempotencyReplayAuthorizationTest
 *
 * @category Test
 */
final class NotificationHttpIdempotencyReplayAuthorizationTest extends TestCase
{
  #[Test]
  public function currentForeignOwnerDeniesEvenWithOldSignedProof(): void
  {
    $database = $this->createStub(Connection::class);
    $database->method('fetchAssociative')->willReturn(['id' => 'target', 'recipient_user_id' => 'foreign', 'organization_id' => null]);
    $common = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $common->method('authorize')->willReturn(['principalId' => 'actor']);
    $this->expectException(IdempotencyDeniedException::class);
    new NotificationHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($this->request(), $this->proof('actor', 'target'));
  }

  #[Test]
  public function absentTargetWithoutOriginalOwnerFactsDenies(): void
  {
    $database = $this->createStub(Connection::class);
    $database->method('fetchAssociative')->willReturn(false);
    $common = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $common->method('authorize')->willReturn(['principalId' => 'actor']);
    $this->expectException(IdempotencyDeniedException::class);
    new NotificationHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($this->request(), $this->proof('foreign', 'target'));
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
    self::assertSame('actor', new NotificationHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($request, $proof)['notificationRecipientId']);
  }

  #[Test]
  public function liveOwnerProducesBoundOriginalFacts(): void
  {
    $database = $this->createMock(Connection::class);
    $database->expects(self::once())->method('fetchAssociative')->with('SELECT id, recipient_user_id, organization_id FROM notifications WHERE id = ?', ['target'])->willReturn(['id' => 'target', 'recipient_user_id' => 'actor', 'organization_id' => null]);
    $common = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $common->method('authorize')->willReturn(['principalId' => 'actor']);
    self::assertSame(['principalId' => 'actor', 'targetId' => 'target', 'notificationRecipientId' => 'actor', 'notificationOrganizationId' => ''], new NotificationHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($this->request(), null));
  }

  #[Test]
  public function changedNotificationOrganizationDeniesBeforeCommonReplay(): void
  {
    $database = $this->createStub(Connection::class);
    $database->method('fetchAssociative')->willReturn(['id' => 'target', 'recipient_user_id' => 'actor', 'organization_id' => 'new-organization']);
    $common = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $common->expects(self::never())->method('authorize');
    $this->expectException(IdempotencyDeniedException::class);
    new NotificationHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($this->request(), new IdempotencyProof(['notificationRecipientId' => 'actor', 'targetId' => 'target', 'notificationOrganizationId' => 'original-organization'], new DateTimeImmutable('+1 minute')));
  }

  #[Test]
  public function recipientScopeDoesNotInventOrganizationMembershipRequirements(): void
  {
    $request = $this->request();
    $database = $this->createStub(Connection::class);
    $database->method('fetchAssociative')->willReturn(['id' => 'target', 'recipient_user_id' => 'actor', 'organization_id' => 'organization']);
    $common = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $common->expects(self::once())->method('authorize')->with($request, null, null)->willReturn(['principalId' => 'actor']);
    self::assertSame('organization', new NotificationHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($request, null)['notificationOrganizationId']);
  }

  #[Test]
  public function preferenceResultRetainsItsDeclaredNestedSchema(): void
  {
    foreach (NotificationHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ('notification_preferences_update' === $operation->name) {
        $result = new UpdateNotificationPreferencesResult([new NotificationPreferenceResult('organization', false, true, new DateTimeImmutable('2026-10-11T12:00:00+00:00'))]);
        $codec = new HttpIdempotencyResultCodec();
        self::assertEquals($result, $codec->decode($codec->encode($result, $operation), $operation));

        return;
      }
    }
    self::fail('Preference mutation is absent from the owner manifest.');
  }

  private function request(): HttpIdempotencyAuthorizationRequest
  {
    foreach (NotificationHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ('notification_mark_as_read' === $operation->name) {
        return new HttpIdempotencyAuthorizationRequest('actor', null, $operation, '/notification/target', ['id' => 'target'], []);
      }
    }
    self::fail('Operation is absent from the owner manifest.');
  }

  private function proof(string $owner, string $target): IdempotencyProof
  {
    return new IdempotencyProof(['notificationRecipientId' => $owner, 'targetId' => $target], new DateTimeImmutable('+1 minute'));
  }
}
