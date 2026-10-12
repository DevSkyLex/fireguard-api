<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Messaging\Application\Port\Outbound\MessagingSubjectResolverPort;
use Messaging\Infrastructure\Service\Idempotency\MessagingReplayContext;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

use function array_key_exists;
use function in_array;
use function is_string;

/**
 * Class MessagingHttpIdempotencyReplayAuthorizationAdapter
 *
 * Checks fresh conversation visibility and actor participation before decoding message content. Cleanup-only responses retain their original active-member ownership rules.
 *
 * @category Adapter
 */
final readonly class MessagingHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constants
  /**
   * Constant PATCH_MESSAGE
   */
  private const string PATCH_MESSAGE = '_api_/messages/{id}_patch';

  /**
   * Constant DELETE_MESSAGE
   */
  private const string DELETE_MESSAGE = '_api_/messages/{id}_delete';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @param OrdinaryHttpReplayAuthorizationPort $ordinary current actor and signed original requirements
   * @param FreshOrganizationAuthorizationPort $organizations uncached membership and effective grants
   * @param Connection $connection owner main database
   * @param iterable<MessagingSubjectResolverPort> $subjects published owner subject existence and permission contributors
   */
  public function __construct(
    private OrdinaryHttpReplayAuthorizationPort $ordinary,
    private FreshOrganizationAuthorizationPort $organizations,
    private Connection $connection,
    private iterable $subjects,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (MessagingHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($operation->name === $operationName) {
        return true;
      }
    }

    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    return $this->ordinary->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $operation = $request->operation->name;
    if (!$this->supports($operation)) {
      $this->deny();
    }
    $original = null === $storedProof ? [] : $storedProof->metadata;
    $context = $this->context($request, $original);
    $organization = $this->string($context, 'organization_id');
    $member = $this->organizations->activeMemberId($request->principalId, $organization);
    if (null === $member || (null !== $storedProof && ($original['messagingMemberId'] ?? null) !== $member)) {
      $this->deny();
    }
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    $facts['messagingMemberId'] = $member;
    $mode = $this->authorizeAccess($request, $context, $member);
    $facts = $this->captureContextFacts($storedProof, $context, $facts);
    $facts = $this->captureDeletedFact($storedProof, $context, $facts, $mode);
    $this->assertTargetOwnership($request, $storedProof, $context, $organization, $member);

    return $facts;
  }

  /**
   * Method context
   *
   * Keeps the signed conversation and owner facts after deleting a child record, then rereads that parent before replay.
   *
   * @param HttpIdempotencyAuthorizationRequest $request trusted route and bounded input
   * @param array<string, string> $proof authenticated original context
   *
   * @return array<string, mixed> fresh owner-local conversation and child facts
   */
  private function context(HttpIdempotencyAuthorizationRequest $request, array $proof): array
  {
    return new MessagingReplayContext($this->connection, $this->subjects)->resolve($request, $proof);
  }

  /**
   * Method visibility
   *
   * @param HttpIdempotencyAuthorizationRequest $request caller and operation
   * @param array<string, mixed> $context current conversation
   * @param string $member active actor membership
   * @param bool $write writing requires actual participation even for managers
   *
   * @return void
   */
  private function visibility(HttpIdempotencyAuthorizationRequest $request, array $context, string $member, bool $write): void
  {
    if ('messaging_ping_presence' === $request->operation->name) {
      return;
    }
    $organization = $this->string($context, 'organization_id');
    if ('participants' === ($context['visibility'] ?? null)) {
      if (in_array($request->operation->name, ['_api_/direct-conversations_post'], true) && !isset($context['id'])) {
        return;
      }
      $id = $this->string($context, 'id');
      $participant = $this->connection->fetchOne('SELECT 1 FROM messaging_participants WHERE conversation_id = :conversation AND member_id = :member AND organization_id = :organization', ['conversation' => $id, 'member' => $member, 'organization' => $organization]);
      if (false === $participant && ($write || !$this->organizations->hasPermission($request->principalId, $organization, 'organization.messaging.manage'))) {
        $this->deny();
      }
    } elseif ('subject' === ($context['visibility'] ?? null)) {
      $this->subjectVisibility($request, $context);
    } else {
      $this->deny();
    }
  }

  /**
   * Method mode
   *
   * @param string $operation explicit registered operation
   *
   * @return string original owner authorization mode
   */
  private function mode(string $operation): string
  {
    return match ($operation) {
      '_api_/channels_post', '_api_/channels/{id}_patch', '_api_/channels/{id}_delete', 'messaging_channel_add_participant', 'messaging_channel_remove_participant', 'messaging_channel_bind_team', 'messaging_channel_set_parent', '_api_/conversations/{id}_patch' => 'manage',
      self::DELETE_MESSAGE, 'delete_message_attachment', 'unpin_message', 'remove_reaction', 'unsave_message', 'unfavorite_conversation' => 'cleanup',
      'messaging_ping_presence' => 'membership',
      '_api_/conversations/{conversationId}/messages_post', 'put_message', self::PATCH_MESSAGE, 'pin_message', 'post_reply', 'add_message_attachment', 'messaging_conversation_typing' => 'write',
      default => 'read',
    };
  }

  /**
   * Method ownership
   *
   * @param HttpIdempotencyAuthorizationRequest $request current caller
   * @param string $organization current contextual organization
   * @param string $member active caller membership
   * @param string $owner signed original or current record owner
   *
   * @return void
   */
  private function ownership(HttpIdempotencyAuthorizationRequest $request, string $organization, string $member, string $owner): void
  {
    if ($member !== $owner && !$this->organizations->hasPermission($request->principalId, $organization, 'organization.messaging.manage')) {
      $this->deny();
    }
  }

  /**
   * Method permission
   *
   * @param HttpIdempotencyAuthorizationRequest $request current caller
   * @param string $organization current contextual organization
   * @param string $permission owner-required grant
   *
   * @return void
   */
  private function permission(HttpIdempotencyAuthorizationRequest $request, string $organization, string $permission): void
  {
    if (!$this->organizations->hasPermission($request->principalId, $organization, $permission)) {
      $this->deny();
    }
  }

  /**
   * Method string
   *
   * @param array<string, mixed> $row fresh scalar row or signed scalar facts
   * @param string $key required identity key
   *
   * @return string nonempty current identifier
   */
  private function string(array $row, string $key): string
  {
    $value = $row[$key] ?? null;
    if (!is_string($value) || '' === $value) {
      $this->deny();
    }

    return $value;
  }

  /**
   * Method deny
   *
   * @return never fails before cached content is decoded
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }

  /**
   * Method authorizeAccess
   *
   * Applies the current permission and visibility gate before capturing replay facts.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current caller and route
   * @param array<string, mixed> $context current owner conversation and child facts
   * @param string $member current active membership
   *
   * @return string original owner access mode
   */
  private function authorizeAccess(HttpIdempotencyAuthorizationRequest $request, array $context, string $member): string
  {
    $operation = $request->operation->name;
    $organization = $this->string($context, 'organization_id');
    $mode = $this->mode($operation);
    if ('manage' === $mode) {
      $this->permission($request, $organization, 'organization.messaging.manage');
    } elseif (!in_array($mode, ['cleanup', 'membership'], true)) {
      $this->permission($request, $organization, 'write' === $mode ? 'organization.messaging.write' : 'organization.messaging.read');
      $this->visibility($request, $context, $member, 'write' === $mode);
    }

    return $mode;
  }

  /**
   * Method captureContextFacts
   *
   * Preserves original signed child ownership and conversation facts without coercion.
   *
   * @access private
   *
   * @param IdempotencyProof|null $storedProof verified original receipt
   * @param array<string, mixed> $context current owner conversation and child facts
   * @param array<string, string> $facts current actor and scope facts
   *
   * @return array<string, string> checked owner context facts
   */
  private function captureContextFacts(?IdempotencyProof $storedProof, array $context, array $facts): array
  {
    $original = null === $storedProof ? [] : $storedProof->metadata;
    foreach ([
      'id' => 'messagingConversationId',
      'subject_type' => 'messagingSubjectType',
      'subject_id' => 'messagingSubjectId',
      'visibility' => 'messagingVisibility',
      'target_message_id' => 'messagingTargetMessageId',
      'author_member_id' => 'messagingAuthorMemberId',
      'uploaded_by_member_id' => 'messagingUploaderMemberId',
      'target_attachment_id' => 'messagingTargetAttachmentId',
    ] as $column => $key) {
      if (!array_key_exists($column, $context)) {
        continue;
      }
      $value = $context[$column] ?? '';
      if (!is_string($value)) {
        $this->deny();
      }
      if (null !== $storedProof && isset($original[$key]) && $original[$key] !== $value) {
        $this->deny();
      }
      $facts[$key] = $value;
    }
    if (null === $storedProof && array_key_exists('pinned_by_member_id', $context)) {
      $facts['messagingPinnerMemberId'] = is_string($context['pinned_by_member_id']) ? $context['pinned_by_member_id'] : '';
    }

    return $facts;
  }

  /**
   * Method captureDeletedFact
   *
   * Refuses changed deletion state for content replay while preserving cleanup responses.
   *
   * @access private
   *
   * @param IdempotencyProof|null $storedProof verified original receipt
   * @param array<string, mixed> $context current owner conversation and child facts
   * @param array<string, string> $facts current actor and scope facts
   * @param string $mode original owner access mode
   *
   * @return array<string, string> checked deletion facts
   */
  private function captureDeletedFact(?IdempotencyProof $storedProof, array $context, array $facts, string $mode): array
  {
    $original = null === $storedProof ? [] : $storedProof->metadata;
    if (array_key_exists('deleted_at', $context)) {
      $deleted = null === $context['deleted_at'] ? 'false' : 'true';
      if (null !== $storedProof && 'cleanup' !== $mode && ($original['messagingTargetDeleted'] ?? null) !== $deleted) {
        $this->deny();
      }
      $facts['messagingTargetDeleted'] = $deleted;
    }

    return $facts;
  }

  /**
   * Method assertTargetOwnership
   *
   * Retains author, uploader and original pinner checks for every cleanup operation.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current caller and route
   * @param IdempotencyProof|null $storedProof verified original receipt
   * @param array<string, mixed> $context current owner conversation and child facts
   * @param string $organization current owner organization
   * @param string $member current active membership
   *
   * @return void
   */
  private function assertTargetOwnership(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, array $context, string $organization, string $member): void
  {
    $operation = $request->operation->name;
    $original = null === $storedProof ? [] : $storedProof->metadata;
    if (self::PATCH_MESSAGE === $operation && ($context['author_member_id'] ?? null) !== $member) {
      $this->deny();
    }
    if (self::DELETE_MESSAGE === $operation) {
      $this->ownership($request, $organization, $member, $this->string($context, 'author_member_id'));
    }
    if ('delete_message_attachment' === $operation) {
      $this->ownership($request, $organization, $member, $this->string($context, 'uploaded_by_member_id'));
    }
    if ('unpin_message' === $operation) {
      $pinner = null !== $storedProof ? ($original['messagingPinnerMemberId'] ?? '') : ($context['pinned_by_member_id'] ?? '');
      if (is_string($pinner) && '' !== $pinner) {
        $this->ownership($request, $organization, $member, $pinner);
      }
    }
  }

  /**
   * Method subjectVisibility
   *
   * Requires an installed owner resolver, existing subject and its current read permission.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current caller and route
   * @param array<string, mixed> $context current owner conversation and child facts
   *
   * @return void
   */
  private function subjectVisibility(HttpIdempotencyAuthorizationRequest $request, array $context): void
  {
    $organization = $this->string($context, 'organization_id');
    $type = $this->string($context, 'subject_type');
    $id = $this->string($context, 'subject_id');
    $found = false;
    foreach ($this->subjects as $subject) {
      $descriptor = $subject->descriptor();
      if ($descriptor->type !== $type) {
        continue;
      }
      $found = true;
      if (!$subject->resolve($organization, $id)->exists) {
        $this->deny();
      }
      $this->permission($request, $organization, $descriptor->requiredReadPermission);

      break;
    }
    if (!$found) {
      $this->deny();
    }
  }
  // #endregion
}
