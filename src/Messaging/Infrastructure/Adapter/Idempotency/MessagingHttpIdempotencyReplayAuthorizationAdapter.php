<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Messaging\Application\Port\Outbound\MessagingSubjectResolverPort;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Contract\Http\{HttpIdempotencyAuthorizationRequest, ResourceIriParser};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use Symfony\Component\Uid\Uuid;

use function array_key_exists;
use function array_replace;
use function in_array;
use function is_array;
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
    $mode = $this->mode($operation);
    if ('manage' === $mode) {
      $this->permission($request, $organization, 'organization.messaging.manage');
    } elseif (!in_array($mode, ['cleanup', 'membership'], true)) {
      $this->permission($request, $organization, 'write' === $mode ? 'organization.messaging.write' : 'organization.messaging.read');
      $this->visibility($request, $context, $member, 'write' === $mode);
    }

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
    if (array_key_exists('deleted_at', $context)) {
      $deleted = null === $context['deleted_at'] ? 'false' : 'true';
      if (null !== $storedProof && 'cleanup' !== $mode && ($original['messagingTargetDeleted'] ?? null) !== $deleted) {
        $this->deny();
      }
      $facts['messagingTargetDeleted'] = $deleted;
    }
    if ('_api_/messages/{id}_patch' === $operation && ($context['author_member_id'] ?? null) !== $member) {
      $this->deny();
    }
    if ('_api_/messages/{id}_delete' === $operation) {
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
    $operation = $request->operation->name;
    if (in_array($operation, ['_api_/channels_post', '_api_/conversations_post', '_api_/direct-conversations_post', 'messaging_ping_presence'], true)) {
      $body = $request->canonicalInput['body'] ?? null;
      $value = is_array($body) ? ($body['value'] ?? null) : null;
      $fields = is_array($value) ? ($value['fields'] ?? null) : null;
      if (!is_array($fields) || !is_string($fields['organization'] ?? null)) {
        $this->deny();
      }

      try {
        $organization = ResourceIriParser::id($fields['organization'], 'organizations');
      } catch (InvalidArgumentException) {
        $this->deny();
      }
      if (!Uuid::isValid($organization)) {
        $this->deny();
      }
      if ([] !== $proof && 'messaging_ping_presence' !== $operation) {
        $id = $proof['resourceId'] ?? '';
        if (($proof['resourceKind'] ?? null) !== 'messaging_conversation') {
          $this->deny();
        }
        $conversation = $this->conversation($id);
        if (($conversation['organization_id'] ?? null) !== $organization) {
          $this->deny();
        }

        return $conversation;
      }
      $subject = '_api_/conversations_post' === $operation ? ($fields['subjectType'] ?? null) : 'channel';
      $subjectId = '_api_/conversations_post' === $operation ? ($fields['subject'] ?? null) : null;
      if ('_api_/direct-conversations_post' === $operation) {
        $subject = 'direct';
      }
      if (!is_string($subject)) {
        $this->deny();
      }
      if ('_api_/conversations_post' === $operation) {
        $subjectId = $this->subjectId($subject, $subjectId);
      }

      return ['organization_id' => $organization, 'subject_type' => $subject, 'subject_id' => $subjectId, 'visibility' => 'channel' === $subject || 'direct' === $subject ? 'participants' : 'subject'];
    }

    $child = [];
    if ('delete_message_attachment' === $operation) {
      $id = $this->routeId($request, 'id');
      $child = $this->connection->fetchAssociative('SELECT id AS target_attachment_id, conversation_id, organization_id, uploaded_by_member_id FROM messaging_attachments WHERE id = :id', ['id' => $id]);
      if (false === $child) {
        if (($proof['resourceId'] ?? null) !== $id || ($proof['resourceKind'] ?? null) !== 'messaging_attachment') {
          $this->deny();
        }
        $child = ['target_attachment_id' => $id, 'conversation_id' => $proof['messagingConversationId'] ?? '', 'organization_id' => $proof['organizationId'] ?? '', 'uploaded_by_member_id' => $proof['messagingUploaderMemberId'] ?? ''];
      }
      $conversationId = $this->string($child, 'conversation_id');
    } elseif (in_array($operation, ['_api_/messages/{id}_patch', '_api_/messages/{id}_delete', 'pin_message', 'unpin_message', 'add_reaction', 'remove_reaction', 'post_reply', 'save_message', 'unsave_message', 'add_message_attachment'], true)) {
      $id = $this->routeId($request, 'add_message_attachment' === $operation ? 'messageId' : 'id');
      $child = $this->connection->fetchAssociative('SELECT id AS target_message_id, conversation_id, organization_id, author_member_id, pinned_by_member_id, deleted_at FROM messaging_messages WHERE id = :id', ['id' => $id]);
      if (false === $child) {
        if ('_api_/messages/{id}_delete' !== $operation || ($proof['resourceId'] ?? null) !== $id || ($proof['resourceKind'] ?? null) !== 'messaging_message') {
          $this->deny();
        }
        $child = ['target_message_id' => $id, 'conversation_id' => $proof['messagingConversationId'] ?? '', 'organization_id' => $proof['organizationId'] ?? '', 'author_member_id' => $proof['messagingAuthorMemberId'] ?? ''];
      }
      $conversationId = $this->string($child, 'conversation_id');
      if ([] !== $proof && ($proof['messagingTargetMessageId'] ?? null) !== $id) {
        $this->deny();
      }
    } else {
      $conversationId = $this->routeId($request, in_array($operation, ['_api_/conversations/{conversationId}/messages_post', 'put_message'], true) ? 'conversationId' : 'id');
    }
    $conversation = $this->conversation($conversationId);
    if ([] !== $child && ($conversation['organization_id'] ?? null) !== ($child['organization_id'] ?? null)) {
      $this->deny();
    }
    if ([] !== $proof) {
      $resultId = $proof['resourceId'] ?? null;
      if (is_string($resultId) && 'messaging_message' === ($proof['resourceKind'] ?? null)) {
        if (in_array($operation, ['_api_/conversations/{conversationId}/messages_post', 'put_message', 'post_reply'], true)) {
          $created = $this->connection->fetchAssociative('SELECT conversation_id, parent_message_id, deleted_at FROM messaging_messages WHERE id = :id', ['id' => $resultId]);
          if (false === $created || ($created['conversation_id'] ?? null) !== $conversationId
            || null !== ($created['deleted_at'] ?? null)
            || ('post_reply' === $operation && ($created['parent_message_id'] ?? null) !== ($child['target_message_id'] ?? null))) {
            $this->deny();
          }
        } elseif ($resultId !== ($child['target_message_id'] ?? null)) {
          $this->deny();
        }
      }
      if ('add_message_attachment' === $operation) {
        if (!is_string($resultId) || ($proof['resourceKind'] ?? null) !== 'messaging_attachment') {
          $this->deny();
        }
        $attachment = $this->connection->fetchAssociative('SELECT message_id, conversation_id, organization_id FROM messaging_attachments WHERE id = :id', ['id' => $resultId]);
        if (false === $attachment || ($attachment['message_id'] ?? null) !== ($child['target_message_id'] ?? null)
          || ($attachment['conversation_id'] ?? null) !== $conversationId || ($attachment['organization_id'] ?? null) !== ($conversation['organization_id'] ?? null)) {
          $this->deny();
        }
      }
    }

    return array_replace($conversation, $child);
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
      '_api_/messages/{id}_delete', 'delete_message_attachment', 'unpin_message', 'remove_reaction', 'unsave_message', 'unfavorite_conversation' => 'cleanup',
      'messaging_ping_presence' => 'membership',
      '_api_/conversations/{conversationId}/messages_post', 'put_message', '_api_/messages/{id}_patch', 'pin_message', 'post_reply', 'add_message_attachment', 'messaging_conversation_typing' => 'write',
      default => 'read',
    };
  }

  /**
   * Method conversation
   *
   * @param string $id current contextual parent identifier
   *
   * @return array<string, mixed> uncached parent context
   */
  private function conversation(string $id): array
  {
    if (!Uuid::isValid($id)) {
      $this->deny();
    }
    $row = $this->connection->fetchAssociative('SELECT id, organization_id, subject_type, subject_id, visibility FROM messaging_conversations WHERE id = :id', ['id' => $id]);
    if (false === $row) {
      $this->deny();
    }

    return $row;
  }

  /**
   * Method subjectId
   *
   * @param string $type registered subject key
   * @param mixed $value canonical body subject IRI
   *
   * @return string parsed scoped subject identifier
   */
  private function subjectId(string $type, mixed $value): string
  {
    if (!is_string($value)) {
      $this->deny();
    }
    foreach ($this->subjects as $subject) {
      $descriptor = $subject->descriptor();
      if ($descriptor->type === $type) {
        try {
          $id = ResourceIriParser::id($value, $descriptor->resourceSegment);
        } catch (InvalidArgumentException) {
          $this->deny();
        }
        if (!Uuid::isValid($id)) {
          $this->deny();
        }

        return $id;
      }
    }
    $this->deny();
  }

  /**
   * Method routeId
   *
   * @param HttpIdempotencyAuthorizationRequest $request trusted route facts
   * @param string $key explicit owner route key
   *
   * @return string validated identifier
   */
  private function routeId(HttpIdempotencyAuthorizationRequest $request, string $key): string
  {
    $id = $request->uriVariables[$key] ?? null;
    if (!is_string($id) || !Uuid::isValid($id)) {
      $this->deny();
    }

    return $id;
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
}
