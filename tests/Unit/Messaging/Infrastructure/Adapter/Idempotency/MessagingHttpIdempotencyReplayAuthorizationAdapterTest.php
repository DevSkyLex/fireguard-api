<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Messaging\Application\Contract\Subject\{MessagingSubjectDescriptor, MessagingSubjectResolution};
use Messaging\Application\Port\Outbound\MessagingSubjectResolverPort;
use Messaging\Infrastructure\Adapter\Idempotency\{MessagingHttpIdempotencyOperationContributionAdapter, MessagingHttpIdempotencyReplayAuthorizationAdapter};
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Port\Outbound\Idempotency\OrdinaryHttpReplayAuthorizationPort;
use stdClass;

use function array_replace;
use function str_contains;

/**
 * Class MessagingHttpIdempotencyReplayAuthorizationAdapterTest
 *
 * Verifies permission and participant changes cannot reveal saved content while owner-only cleanup keeps its deliberately narrower gate.
 *
 * @category Test
 */
final class MessagingHttpIdempotencyReplayAuthorizationAdapterTest extends TestCase
{
  private const string USER = '019c6f20-0000-7000-8000-000000000001';

  private const string ORG = '019c6f20-0000-7000-8000-000000000002';

  private const string MEMBER = '019c6f20-0000-7000-8000-000000000003';

  private const string CONVERSATION = '019c6f20-0000-7000-8000-000000000004';

  private const string MESSAGE = '019c6f20-0000-7000-8000-000000000005';

  private const string ATTACHMENT = '019c6f20-0000-7000-8000-000000000006';

  private const string OTHER = '019c6f20-0000-7000-8000-000000000007';

  /**
   * Method testRemovingParticipationDeniesSavedContentButKeepsLegitimateCleanup
   */
  public function testRemovingParticipationDeniesSavedContentButKeepsLegitimateCleanup(): void
  {
    $state = $this->state();
    $adapter = $this->adapter($state);
    $save = $this->request('save_message');
    $proof = $this->proof($adapter->authorize($save, null), 'messaging_message', self::MESSAGE);
    self::assertSame(self::MEMBER, $adapter->authorize($save, $proof)['messagingMemberId']);

    $state->participant = false;
    $this->assertDenied(fn () => $adapter->authorize($save, $proof));

    $state->read = false;
    $cleanup = $this->request('unsave_message');
    $cleanupProof = $this->proof($adapter->authorize($cleanup, null), 'messaging_message', self::MESSAGE);
    self::assertSame(self::ORG, $adapter->authorize($cleanup, $cleanupProof)['organizationId']);
    $state->member = null;
    $this->assertDenied(fn () => $adapter->authorize($cleanup, $cleanupProof));
  }

  /**
   * Method testModeratorMayReadWithoutParticipationButCannotWriteWithoutIt
   */
  public function testModeratorMayReadWithoutParticipationButCannotWriteWithoutIt(): void
  {
    $state = $this->state();
    $state->participant = false;
    $state->manage = true;
    $adapter = $this->adapter($state);
    self::assertSame(self::MEMBER, $adapter->authorize($this->request('save_message'), null)['messagingMemberId']);
    $this->assertDenied(fn () => $adapter->authorize($this->request('pin_message'), null));
  }

  /**
   * Method testLosingReadPermissionOrReplacingMembershipDeniesSavedContent
   */
  public function testLosingReadPermissionOrReplacingMembershipDeniesSavedContent(): void
  {
    $state = $this->state();
    $adapter = $this->adapter($state);
    $request = $this->request('save_message');
    $proof = $this->proof($adapter->authorize($request, null), 'messaging_message', self::MESSAGE);
    $state->read = false;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
    $state->read = true;
    $state->member = self::OTHER;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
  }

  /**
   * Method testTombstoningMessageDeniesReplayOfItsOriginalBody
   */
  public function testTombstoningMessageDeniesReplayOfItsOriginalBody(): void
  {
    $state = $this->state();
    $adapter = $this->adapter($state);
    $request = $this->request('save_message');
    $proof = $this->proof($adapter->authorize($request, null), 'messaging_message', self::MESSAGE);
    $state->deletedAt = '2026-10-12 10:00:00';
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
  }

  /**
   * Method testDeletedAttachmentKeepsSignedUploaderAndCurrentParentScope
   */
  public function testDeletedAttachmentKeepsSignedUploaderAndCurrentParentScope(): void
  {
    $state = $this->state();
    $adapter = $this->adapter($state);
    $request = $this->request('delete_message_attachment', ['id' => self::ATTACHMENT]);
    $proof = $this->proof($adapter->authorize($request, null), 'messaging_attachment', self::ATTACHMENT);
    $state->attachmentExists = false;
    $state->participant = false;
    $state->read = false;
    self::assertSame(self::MEMBER, $adapter->authorize($request, $proof)['messagingUploaderMemberId']);
    $state->conversationOrganization = self::OTHER;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
  }

  /**
   * Method testModeratorCannotReuseDeleteAfterLosingManagementEvenWhenChildIsGone
   */
  public function testModeratorCannotReuseDeleteAfterLosingManagementEvenWhenChildIsGone(): void
  {
    $state = $this->state();
    $state->author = self::OTHER;
    $state->manage = true;
    $adapter = $this->adapter($state);
    $request = $this->request('_api_/messages/{id}_delete');
    $proof = $this->proof($adapter->authorize($request, null), 'messaging_message', self::MESSAGE);
    $state->messageExists = false;
    $state->manage = false;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
    $state->manage = true;
    self::assertSame(self::OTHER, $adapter->authorize($request, $proof)['messagingAuthorMemberId']);
  }

  /**
   * Method testUnpinChecksOriginalPinnerAfterSuccessfulMutationClearsIt
   */
  public function testUnpinChecksOriginalPinnerAfterSuccessfulMutationClearsIt(): void
  {
    $state = $this->state();
    $state->pinner = self::OTHER;
    $state->manage = true;
    $adapter = $this->adapter($state);
    $request = $this->request('unpin_message');
    $proof = $this->proof($adapter->authorize($request, null), 'messaging_message', self::MESSAGE);
    $state->pinner = null;
    $state->manage = false;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
  }

  /**
   * Method testSubjectPermissionLossOrDisappearingSubjectDeniesCachedContent
   */
  public function testSubjectPermissionLossOrDisappearingSubjectDeniesCachedContent(): void
  {
    $state = (object) ['permission' => true, 'exists' => true];
    $organizations = $this->createStub(FreshOrganizationAuthorizationPort::class);
    $organizations->method('activeMemberId')->willReturn(self::MEMBER);
    $organizations->method('hasPermission')->willReturnCallback(static fn (string $user, string $organization, string $permission): bool => 'organization.facilities.read' !== $permission || $state->permission);
    $ordinary = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $ordinary->method('authorize')->willReturnCallback(static fn (HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof, ?string $organization): array => null === $proof ? ['organizationId' => $organization] : $proof->metadata);
    $subject = $this->createStub(MessagingSubjectResolverPort::class);
    $subject->method('descriptor')->willReturn(new MessagingSubjectDescriptor('facility', 'Facility', 'facilities', 'organization.facilities.read'));
    $subject->method('resolve')->willReturnCallback(static fn (): MessagingSubjectResolution => new MessagingSubjectResolution($state->exists, 'Fixture', 'organization.facilities.read'));
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturnCallback(static fn (string $sql): array => str_contains($sql, 'FROM messaging_messages')
      ? ['target_message_id' => self::MESSAGE, 'conversation_id' => self::CONVERSATION, 'organization_id' => self::ORG, 'author_member_id' => self::MEMBER, 'pinned_by_member_id' => null, 'deleted_at' => null]
      : ['id' => self::CONVERSATION, 'organization_id' => self::ORG, 'subject_type' => 'facility', 'subject_id' => self::OTHER, 'visibility' => 'subject']);
    $adapter = new MessagingHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $connection, [$subject]);
    $request = $this->request('save_message');
    $proof = $this->proof($adapter->authorize($request, null), 'messaging_message', self::MESSAGE);
    self::assertSame('facility', $adapter->authorize($request, $proof)['messagingSubjectType']);
    $state->permission = false;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
    $state->permission = true;
    $state->exists = false;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
  }

  /**
   * Method state
   *
   * @return object{participant: bool, read: bool, write: bool, manage: bool, member: ?string, author: string, pinner: ?string, messageExists: bool, attachmentExists: bool, conversationOrganization: string}&stdClass mutable external state
   */
  private function state(): object
  {
    return (object) [
      'participant' => true, 'read' => true, 'write' => true, 'manage' => false,
      'member' => self::MEMBER, 'author' => self::MEMBER, 'pinner' => self::MEMBER,
      'messageExists' => true, 'attachmentExists' => true, 'conversationOrganization' => self::ORG, 'deletedAt' => null,
    ];
  }

  /**
   * Method adapter
   *
   * @param object{participant: bool, read: bool, write: bool, manage: bool, member: ?string, author: string, pinner: ?string, messageExists: bool, attachmentExists: bool, conversationOrganization: string}&stdClass $state current external state
   *
   * @return MessagingHttpIdempotencyReplayAuthorizationAdapter test boundary
   */
  private function adapter(object $state): MessagingHttpIdempotencyReplayAuthorizationAdapter
  {
    $ordinary = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $ordinary->method('authorize')->willReturnCallback(static fn (HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof, ?string $organization): array => null === $proof ? ['organizationId' => $organization] : $proof->metadata);
    $organization = $this->createStub(FreshOrganizationAuthorizationPort::class);
    $organization->method('activeMemberId')->willReturnCallback(static fn (): ?string => $state->member);
    $organization->method('hasPermission')->willReturnCallback(static fn (string $user, string $org, string $permission): bool => match ($permission) {
      'organization.messaging.read' => $state->read,
      'organization.messaging.write' => $state->write,
      'organization.messaging.manage' => $state->manage,
      default => false,
    });
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchOne')->willReturnCallback(static fn (): int|false => $state->participant ? 1 : false);
    $connection->method('fetchAssociative')->willReturnCallback(static function (string $sql) use ($state): array|false {
      if (str_contains($sql, 'FROM messaging_attachments')) {
        return $state->attachmentExists ? ['target_attachment_id' => self::ATTACHMENT, 'conversation_id' => self::CONVERSATION, 'organization_id' => self::ORG, 'uploaded_by_member_id' => self::MEMBER] : false;
      }
      if (str_contains($sql, 'FROM messaging_messages')) {
        return $state->messageExists ? ['target_message_id' => self::MESSAGE, 'conversation_id' => self::CONVERSATION, 'organization_id' => self::ORG, 'author_member_id' => $state->author, 'pinned_by_member_id' => $state->pinner, 'deleted_at' => $state->deletedAt ?? null] : false;
      }

      return ['id' => self::CONVERSATION, 'organization_id' => $state->conversationOrganization, 'subject_type' => 'channel', 'subject_id' => null, 'visibility' => 'participants'];
    });

    return new MessagingHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organization, $connection, []);
  }

  /**
   * Method request
   *
   * @param string $name registered mutation
   * @param array<string, string> $variables route variables
   *
   * @return HttpIdempotencyAuthorizationRequest current caller request
   */
  private function request(string $name, array $variables = ['id' => self::MESSAGE]): HttpIdempotencyAuthorizationRequest
  {
    foreach (MessagingHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($operation->name === $name) {
        return new HttpIdempotencyAuthorizationRequest(self::USER, null, $operation, '/api/test', $variables, []);
      }
    }
    self::fail('Missing operation.');
  }

  /**
   * Method proof
   *
   * @param array<string, string> $facts first execution authorized context
   * @param string $kind exact resource kind
   * @param string $id original result identifier
   *
   * @return IdempotencyProof authenticated first response facts
   */
  private function proof(array $facts, string $kind, string $id): IdempotencyProof
  {
    return new IdempotencyProof(array_replace($facts, ['resourceKind' => $kind, 'resourceId' => $id]), new DateTimeImmutable('+5 minutes'));
  }

  /**
   * Method assertDenied
   *
   * @param callable(): mixed $attempt current gate attempt
   */
  private function assertDenied(callable $attempt): void
  {
    try {
      $attempt();
      self::fail('A changed current authorization must deny response decoding.');
    } catch (IdempotencyDeniedException $failure) {
      self::assertSame('access_denied', $failure->reason);
    }
  }
}
