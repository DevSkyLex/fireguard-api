<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Service\Idempotency;

use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Messaging\Application\Port\Outbound\MessagingSubjectResolverPort;
use Shared\Application\Contract\Http\{HttpIdempotencyAuthorizationRequest, ResourceIriParser};
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Symfony\Component\Uid\Uuid;

use function array_replace;
use function in_array;
use function is_array;
use function is_string;

/**
 * Class MessagingReplayContext
 *
 * Resolves fresh owner-local parents and child facts while retaining signed deleted-target scope.
 *
 * @category Service
 */
final readonly class MessagingReplayContext
{
  // #region Constants
  /**
   * Constant DELETE_MESSAGE
   */
  private const string DELETE_MESSAGE = '_api_/messages/{id}_delete';

  /**
   * Constant CREATE_CONVERSATION
   */
  private const string CREATE_CONVERSATION = '_api_/conversations_post';

  /**
   * Constant CREATE_DIRECT_CONVERSATION
   */
  private const string CREATE_DIRECT_CONVERSATION = '_api_/direct-conversations_post';

  /**
   * Constant POST_MESSAGE
   */
  private const string POST_MESSAGE = '_api_/conversations/{conversationId}/messages_post';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Receives only the original main connection and registered owner subject resolvers.
   *
   * @access public
   *
   * @param Connection $connection explicitly selected main connection
   * @param iterable<MessagingSubjectResolverPort> $subjects registered owner subjects
   *
   * @return void
   */
  public function __construct(private Connection $connection, private iterable $subjects)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method resolve
   *
   * Resolves parents before checking that child and committed result scopes still match.
   *
   * @access public
   *
   * @param HttpIdempotencyAuthorizationRequest $request current caller and route
   * @param array<string, string> $proof authenticated original context
   *
   * @return array<string, mixed> fresh owner-local replay context
   */
  public function resolve(HttpIdempotencyAuthorizationRequest $request, array $proof): array
  {
    $operation = $request->operation->name;
    if (in_array($operation, ['_api_/channels_post', self::CREATE_CONVERSATION, self::CREATE_DIRECT_CONVERSATION, 'messaging_ping_presence'], true)) {
      return $this->creationContext($request, $proof);
    }
    $child = [];
    if ('delete_message_attachment' === $operation) {
      $child = $this->attachmentContext($request, $proof);
      $conversationId = $this->string($child, 'conversation_id');
    } elseif (in_array($operation, ['_api_/messages/{id}_patch', self::DELETE_MESSAGE, 'pin_message', 'unpin_message', 'add_reaction', 'remove_reaction', 'post_reply', 'save_message', 'unsave_message', 'add_message_attachment'], true)) {
      $child = $this->messageContext($request, $proof);
      $conversationId = $this->string($child, 'conversation_id');
    } else {
      $conversationId = $this->routeId($request, in_array($operation, [self::POST_MESSAGE, 'put_message'], true) ? 'conversationId' : 'id');
    }
    $conversation = $this->conversation($conversationId);
    if ([] !== $child && ($conversation['organization_id'] ?? null) !== ($child['organization_id'] ?? null)) {
      $this->deny();
    }
    if ([] !== $proof) {
      $this->assertCreatedResources($request, $proof, $child, $conversation, $conversationId);
    }

    return array_replace($conversation, $child);
  }

  /**
   * Method creationContext
   *
   * Binds creation input to its original conversation and current owner organization.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current caller and route
   * @param array<string, string> $proof authenticated original context
   *
   * @return array<string, mixed> initial or committed conversation context
   */
  private function creationContext(HttpIdempotencyAuthorizationRequest $request, array $proof): array
  {
    $operation = $request->operation->name;
    $fields = $this->creationFields($request);
    $organization = $this->creationOrganization($fields);

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

    return $this->initialSubjectContext($operation, $fields, $organization);
  }

  /**
   * Method creationFields
   *
   * Preserves the exact organization field validation before parsing its owner IRI.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current caller and route
   *
   * @return array<array-key, mixed>&array{organization: string} structurally valid canonical fields
   */
  private function creationFields(HttpIdempotencyAuthorizationRequest $request): array
  {
    $body = $request->canonicalInput['body'] ?? null;
    $value = is_array($body) ? ($body['value'] ?? null) : null;
    $fields = is_array($value) ? ($value['fields'] ?? null) : null;
    if (!is_array($fields) || !is_string($fields['organization'] ?? null)) {
      $this->deny();
    }

    return $fields;
  }

  /**
   * Method creationOrganization
   *
   * Requires the expected organization IRI and a valid UUID before any owner query.
   *
   * @access private
   *
   * @param array<array-key, mixed>&array{organization: string} $fields canonical submitted fields
   *
   * @return string validated organization identifier
   */
  private function creationOrganization(array $fields): string
  {
    try {
      $organization = ResourceIriParser::id($fields['organization'], 'organizations');
    } catch (InvalidArgumentException) {
      $this->deny();
    }
    if (!Uuid::isValid($organization)) {
      $this->deny();
    }

    return $organization;
  }

  /**
   * Method initialSubjectContext
   *
   * Preserves channel, direct and registered subject visibility for a first execution.
   *
   * @access private
   *
   * @param string $operation registered owner operation
   * @param array<array-key, mixed> $fields canonical submitted fields
   * @param string $organization current owner organization
   *
   * @return array<string, mixed> initial owner subject facts
   */
  private function initialSubjectContext(string $operation, array $fields, string $organization): array
  {
    $subject = self::CREATE_CONVERSATION === $operation ? ($fields['subjectType'] ?? null) : 'channel';
    $subjectId = self::CREATE_CONVERSATION === $operation ? ($fields['subject'] ?? null) : null;
    if (self::CREATE_DIRECT_CONVERSATION === $operation) {
      $subject = 'direct';
    }
    if (!is_string($subject)) {
      $this->deny();
    }
    if (self::CREATE_CONVERSATION === $operation) {
      $subjectId = $this->subjectId($subject, $subjectId);
    }

    return ['organization_id' => $organization, 'subject_type' => $subject, 'subject_id' => $subjectId, 'visibility' => 'channel' === $subject || 'direct' === $subject ? 'participants' : 'subject'];
  }

  /**
   * Method attachmentContext
   *
   * Uses signed deleted-attachment facts only when the exact result identifier and kind match.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current caller and route
   * @param array<string, string> $proof authenticated original context
   *
   * @return array<string, mixed> attachment owner facts
   */
  private function attachmentContext(HttpIdempotencyAuthorizationRequest $request, array $proof): array
  {
    $id = $this->routeId($request, 'id');
    $child = $this->connection->fetchAssociative('SELECT id AS target_attachment_id, conversation_id, organization_id, uploaded_by_member_id FROM messaging_attachments WHERE id = :id', ['id' => $id]);
    if (false === $child) {
      if (($proof['resourceId'] ?? null) !== $id || ($proof['resourceKind'] ?? null) !== 'messaging_attachment') {
        $this->deny();
      }
      $child = ['target_attachment_id' => $id, 'conversation_id' => $proof['messagingConversationId'] ?? '', 'organization_id' => $proof['organizationId'] ?? '', 'uploaded_by_member_id' => $proof['messagingUploaderMemberId'] ?? ''];
    }

    return $child;
  }

  /**
   * Method messageContext
   *
   * Allows a removed message only for its matching original DELETE result proof.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current caller and route
   * @param array<string, string> $proof authenticated original context
   *
   * @return array<string, mixed> message owner facts
   */
  private function messageContext(HttpIdempotencyAuthorizationRequest $request, array $proof): array
  {
    $operation = $request->operation->name;
    $id = $this->routeId($request, 'add_message_attachment' === $operation ? 'messageId' : 'id');
    $child = $this->connection->fetchAssociative('SELECT id AS target_message_id, conversation_id, organization_id, author_member_id, pinned_by_member_id, deleted_at FROM messaging_messages WHERE id = :id', ['id' => $id]);
    if (false === $child) {
      if (self::DELETE_MESSAGE !== $operation || ($proof['resourceId'] ?? null) !== $id || ($proof['resourceKind'] ?? null) !== 'messaging_message') {
        $this->deny();
      }
      $child = ['target_message_id' => $id, 'conversation_id' => $proof['messagingConversationId'] ?? '', 'organization_id' => $proof['organizationId'] ?? '', 'author_member_id' => $proof['messagingAuthorMemberId'] ?? ''];
    }
    $this->string($child, 'conversation_id');
    if ([] !== $proof && ($proof['messagingTargetMessageId'] ?? null) !== $id) {
      $this->deny();
    }

    return $child;
  }

  /**
   * Method assertCreatedResources
   *
   * Rechecks committed creation results before their content can be replayed.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current caller and route
   * @param array<string, string> $proof authenticated original context
   * @param array<string, mixed> $child current child facts
   * @param array<string, mixed> $conversation current parent facts
   * @param string $conversationId resolved parent identifier
   *
   * @return void
   */
  private function assertCreatedResources(HttpIdempotencyAuthorizationRequest $request, array $proof, array $child, array $conversation, string $conversationId): void
  {
    $operation = $request->operation->name;
    $resultId = $proof['resourceId'] ?? null;
    if (is_string($resultId) && 'messaging_message' === ($proof['resourceKind'] ?? null)) {
      if (in_array($operation, [self::POST_MESSAGE, 'put_message', 'post_reply'], true)) {
        $this->assertCreatedMessage($operation, $resultId, $conversationId, $child);
      } elseif ($resultId !== ($child['target_message_id'] ?? null)) {
        $this->deny();
      }
    }
    if ('add_message_attachment' === $operation) {
      $this->assertCreatedAttachment($resultId, $proof, $conversationId, $child, $conversation);
    }
  }

  /**
   * Method assertCreatedMessage
   *
   * Requires the current created message, unchanged parent and non-deleted content.
   *
   * @access private
   *
   * @param string $operation registered owner operation
   * @param string $resultId signed committed message identifier
   * @param string $conversationId current parent identifier
   * @param array<string, mixed> $child current original target message facts
   *
   * @return void
   */
  private function assertCreatedMessage(string $operation, string $resultId, string $conversationId, array $child): void
  {
    $created = $this->connection->fetchAssociative('SELECT conversation_id, parent_message_id, deleted_at FROM messaging_messages WHERE id = :id', ['id' => $resultId]);
    if (false === $created || ($created['conversation_id'] ?? null) !== $conversationId
      || null !== ($created['deleted_at'] ?? null)
      || ('post_reply' === $operation && ($created['parent_message_id'] ?? null) !== ($child['target_message_id'] ?? null))) {
      $this->deny();
    }
  }

  /**
   * Method assertCreatedAttachment
   *
   * Requires the original attachment kind and its current message, conversation and organization.
   *
   * @access private
   *
   * @param mixed $resultId signed committed attachment identifier
   * @param array<string, string> $proof authenticated original context
   * @param string $conversationId current parent identifier
   * @param array<string, mixed> $child current target message facts
   * @param array<string, mixed> $conversation current parent facts
   *
   * @return void
   */
  private function assertCreatedAttachment(mixed $resultId, array $proof, string $conversationId, array $child, array $conversation): void
  {
    if (!is_string($resultId) || ($proof['resourceKind'] ?? null) !== 'messaging_attachment') {
      $this->deny();
    }
    $attachment = $this->connection->fetchAssociative('SELECT message_id, conversation_id, organization_id FROM messaging_attachments WHERE id = :id', ['id' => $resultId]);
    if (false === $attachment || ($attachment['message_id'] ?? null) !== ($child['target_message_id'] ?? null)
      || ($attachment['conversation_id'] ?? null) !== $conversationId || ($attachment['organization_id'] ?? null) !== ($conversation['organization_id'] ?? null)) {
      $this->deny();
    }
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
  // #endregion
}
