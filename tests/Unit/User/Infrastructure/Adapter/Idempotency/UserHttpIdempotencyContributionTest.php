<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Message\VoidResult;
use Shared\Application\Port\Outbound\Idempotency\OrdinaryHttpReplayAuthorizationPort;
use User\Application\UseCase\Command\Avatar\UploadUserAvatar\{UploadUserAvatarCommand, UploadUserAvatarResult};
use User\Application\UseCase\Command\User\ActivateUser\ActivateUserCommand;
use User\Application\UseCase\Command\User\CreateUser\CreateUserResult;
use User\Application\UseCase\Query\User\GetCurrentUserProfile\GetCurrentUserProfileQuery;
use User\Application\UseCase\Query\User\GetUser\GetUserQuery;
use User\Infrastructure\Adapter\Idempotency\{UserHttpIdempotencyOperationContributionAdapter, UserHttpIdempotencyReplayAuthorizationAdapter, UserHttpIdempotencyResponseProjectionAdapter, UserHttpIdempotencyResultProofAdapter};

/**
 * Class UserHttpIdempotencyContributionTest
 *
 * @category Test
 */
final class UserHttpIdempotencyContributionTest extends TestCase
{
  #[Test]
  public function changedTargetTenantDeniesBeforeCommonAuthorization(): void
  {
    $database = $this->createMock(Connection::class);
    $database->expects(self::once())->method('fetchAssociative')->with('SELECT id, tenant_id FROM users WHERE id = ?', ['target'])->willReturn(['id' => 'target', 'tenant_id' => 'other-tenant']);
    $common = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $common->expects(self::never())->method('authorize');
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('access_denied');
    new UserHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($this->request('user_update', 'target'), null);
  }

  #[Test]
  public function anUnknownInitialTargetReturnsNotFoundOnlyAfterCurrentActorRightsAreChecked(): void
  {
    $request = $this->request('user_update', 'unknown');
    $database = $this->createStub(Connection::class);
    $database->method('fetchAssociative')->willReturn(false);
    $common = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $common->expects(self::once())->method('authorize')->with($request, null, null)->willReturn(['principalId' => 'actor']);
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('scope_not_found');
    new UserHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($request, null);
  }

  #[Test]
  public function removedTargetRequiresItsSignedOriginalIdentity(): void
  {
    $database = $this->createStub(Connection::class);
    $database->method('fetchAssociative')->willReturn(false);
    $common = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $common->expects(self::never())->method('authorize');
    $this->expectException(IdempotencyDeniedException::class);
    new UserHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($this->request('user_delete', 'target'), new IdempotencyProof(['targetUserId' => 'different'], new DateTimeImmutable('+1 minute')));
  }

  #[Test]
  public function signedDeletedTargetStillRechecksCurrentActorAndRights(): void
  {
    $request = $this->request('user_delete', 'target');
    $proof = new IdempotencyProof(['targetUserId' => 'target'], new DateTimeImmutable('+1 minute'));
    $database = $this->createStub(Connection::class);
    $database->method('fetchAssociative')->willReturn(false);
    $common = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $common->expects(self::once())->method('authorize')->with($request, $proof, null)->willReturn(['principalId' => 'actor']);
    self::assertSame(['principalId' => 'actor', 'targetUserId' => 'target', 'user_id' => 'target'], new UserHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($request, $proof));
  }

  #[Test]
  public function adminMutationAssociatesOnlyTheFreshlyVerifiedSubjectWithoutChangingItsActor(): void
  {
    $request = $this->request('user_update', 'target');
    $database = $this->createMock(Connection::class);
    $database->expects(self::once())->method('fetchAssociative')->with('SELECT id, tenant_id FROM users WHERE id = ?', ['target'])->willReturn(['id' => 'target', 'tenant_id' => null]);
    $common = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $common->expects(self::once())->method('authorize')->with($request, null, null)->willReturn(['principalId' => 'actor']);
    $facts = new UserHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($request, null);
    self::assertSame('actor', $facts['principalId']);
    self::assertSame('target', $facts['targetUserId']);
    self::assertSame('target', $facts['user_id']);
  }

  #[Test]
  public function aConflictingSignedSubjectCannotBeReassociatedWithAnotherTarget(): void
  {
    $database = $this->createMock(Connection::class);
    $database->expects(self::never())->method('fetchAssociative');
    $common = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $common->expects(self::never())->method('authorize');
    $this->expectException(IdempotencyDeniedException::class);
    new UserHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($this->request('user_update', 'target'), new IdempotencyProof(['targetUserId' => 'target', 'user_id' => 'another-target'], new DateTimeImmutable('+1 minute')));
  }

  #[Test]
  public function canonicalCreateTenantCannotSelectAnotherScope(): void
  {
    $request = $this->request('user_create', null, ['body' => ['kind' => 'json', 'value' => ['kind' => 'object', 'fields' => ['tenantId' => 'foreign']]]]);
    $database = $this->createMock(Connection::class);
    $database->expects(self::never())->method('fetchAssociative');
    $common = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $common->expects(self::never())->method('authorize');
    $this->expectException(IdempotencyDeniedException::class);
    new UserHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($request, null);
  }

  #[Test]
  public function selfProfileIgnoresAnInjectedOtherTarget(): void
  {
    $request = $this->request('user_update_current_profile', 'foreign');
    $database = $this->createMock(Connection::class);
    $database->expects(self::once())->method('fetchAssociative')->with('SELECT id, tenant_id FROM users WHERE id = ?', ['actor'])->willReturn(['id' => 'actor', 'tenant_id' => null]);
    $common = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $common->method('authorize')->willReturn(['principalId' => 'actor']);
    self::assertSame('actor', new UserHttpIdempotencyReplayAuthorizationAdapter($common, $database)->authorize($request, null)['targetUserId']);
  }

  #[Test]
  public function currentAvatarCapturesBothDelegatedAndFinalReads(): void
  {
    $factory = new UserHttpIdempotencyResponseProjectionAdapter();
    $queries = $factory->queries('user_upload_current_avatar', new UploadUserAvatarCommand('actor', 'bytes', 'https://example.test', 1), new UploadUserAvatarResult('actor'));
    self::assertEquals([new GetUserQuery('actor'), new GetCurrentUserProfileQuery('actor')], $queries);
  }

  #[Test]
  public function voidStateMutationUsesCommandIdentityForItsProjection(): void
  {
    self::assertEquals([new GetUserQuery('target')], new UserHttpIdempotencyResponseProjectionAdapter()->queries('user_activate', new ActivateUserCommand('target'), new VoidResult()));
  }

  #[Test]
  public function createdUserAssociationComesFromTheFlushedOwnerRowAndPreservesTheActorFacts(): void
  {
    $operation = $this->request('user_create', null)->operation;
    $database = $this->createMock(Connection::class);
    $database->expects(self::once())->method('fetchAssociative')->with('SELECT id, tenant_id FROM users WHERE id = ?', ['generated-target'])->willReturn(['id' => 'generated-target', 'tenant_id' => null]);
    $initial = ['principalId' => 'actor', 'tenantId' => '', 'resourceId' => 'generated-target'];
    $adapter = new UserHttpIdempotencyResultProofAdapter($database);
    self::assertTrue($adapter->supports($operation));
    self::assertFalse($adapter->supports($this->request('user_update', 'target')->operation));
    self::assertSame(['user_id' => 'generated-target', 'targetUserId' => 'generated-target'], $adapter->proofForResult($operation, new CreateUserResult('generated-target'), $initial));
    self::assertSame('actor', $initial['principalId']);
  }

  #[Test]
  public function anUnflushedOrForeignTenantCreateResultCannotPublishASubjectAssociation(): void
  {
    $database = $this->createStub(Connection::class);
    $database->method('fetchAssociative')->willReturn(['id' => 'generated-target', 'tenant_id' => 'foreign-tenant']);
    $this->expectException(IdempotencyDeniedException::class);
    new UserHttpIdempotencyResultProofAdapter($database)->proofForResult($this->request('user_create', null)->operation, new CreateUserResult('generated-target'), ['principalId' => 'actor', 'tenantId' => '', 'resourceId' => 'generated-target']);
  }

  #[Test]
  public function credentialProofPoliciesRemainExclusive(): void
  {
    $adapter = new UserHttpIdempotencyReplayAuthorizationAdapter($this->createStub(OrdinaryHttpReplayAuthorizationPort::class), $this->createStub(Connection::class));
    self::assertFalse($adapter->supports('user_email_change_request'));
    self::assertFalse($adapter->supports('user_email_change_confirm'));
    self::assertFalse($adapter->supports('user_get'));
    self::assertTrue($adapter->supports('user_email_change_cancel'));
  }

  /**
   * @param array<string, mixed> $input canonical logical request
   */
  private function request(string $name, ?string $target, array $input = []): HttpIdempotencyAuthorizationRequest
  {
    foreach (UserHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($operation->name === $name) {
        return new HttpIdempotencyAuthorizationRequest('actor', null, $operation, '/users/' . ($target ?? ''), null === $target ? [] : ['id' => $target], $input);
      }
    }
    self::fail('Operation is absent from the owner manifest.');
  }
}
