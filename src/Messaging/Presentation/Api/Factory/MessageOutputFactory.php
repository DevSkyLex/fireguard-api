<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Factory;

use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Contract\Reaction\MessageReactionView;
use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingMemberDirectoryPort, MessagingReactionRepositoryPort, MessagingSavedMessageRepositoryPort};
use Messaging\Domain\Model\Attachment\MessagingAttachment;
use Messaging\Presentation\Api\Dto\Output\MessageOutput;

use function array_combine;
use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function in_array;
use function ksort;

use const SORT_STRING;

/**
 * Factory MessageOutputFactory.
 *
 * @category Factory
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessageOutputFactory
{
  // #region Constructor
  public function __construct(
    private readonly MessagingAttachmentRepositoryPort $attachments,
    private readonly MessageAttachmentOutputFactory $attachmentMapper,
    private readonly MessagingReactionRepositoryPort $reactions,
    private readonly MessagingSavedMessageRepositoryPort $savedMessages,
    private readonly MessagingMemberDirectoryPort $memberDirectory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromView.
   *
   * Maps a single message. Batch-loads that one message's attachments and
   * reactions — call sites mapping a whole page of messages MUST use
   * {@see self::fromViews()} instead, to avoid one query per message (N+1).
   *
   * @since 1.0.0
   *
   * @param MessageView $view the message view
   * @param string $currentMemberId the CURRENT (reading) member's identifier,
   *                                so `reactions[].reactedByMe` is computed
   *                                relative to THIS member and never leaks
   *                                another member's flag
   *
   * @return MessageOutput the message output
   */
  public function fromView(MessageView $view, string $currentMemberId): MessageOutput
  {
    return $this->fromViews([$view], $currentMemberId)[0];
  }

  /**
   * Method fromViews.
   *
   * Redacts `body`/`mentions` when the message is deleted (`deletedAt` set):
   * the persisted record retains them (compliance), but the API contract
   * MUST never expose a tombstoned message's content. Attachments and
   * reactions are redacted the same way — a tombstoned message's entire
   * social surface is hidden, not just its body. Batch-loads every
   * attachment AND every reaction for the whole page in one query each, so
   * mapping N messages never issues N attachment/reaction queries.
   *
   * @since 1.0.0
   *
   * @param list<MessageView> $views the message views
   * @param string $currentMemberId the CURRENT (reading) member's identifier,
   *                                so `reactions[].reactedByMe` is computed
   *                                relative to THIS member and never leaks
   *                                another member's flag
   *
   * @return list<MessageOutput> the message outputs
   */
  public function fromViews(array $views, string $currentMemberId): array
  {
    $messageIds = array_map(static fn (MessageView $view): string => $view->id, $views);
    $attachmentsByMessage = $this->groupAttachmentsByMessageId($this->attachments->findByMessageIds($messageIds));
    $reactionsByMessage = $this->groupReactionsByMessageId($this->reactions->findByMessageIds($messageIds));
    $savedMessageIds = $this->savedMessages->findSavedMessageIds($currentMemberId, $messageIds);
    $displayNames = $this->resolveDisplayNames($views);

    return array_map(
      fn (MessageView $view): MessageOutput => $this->build(
        $view,
        $attachmentsByMessage[$view->id] ?? [],
        $reactionsByMessage[$view->id] ?? [],
        in_array($view->id, $savedMessageIds, true),
        $currentMemberId,
        $displayNames,
      ),
      $views,
    );
  }

  /**
   * Method build.
   *
   * @since 1.0.0
   *
   * @param MessageView $view the message view
   * @param list<MessagingAttachment> $attachments the message's attachments
   * @param list<MessageReactionView> $reactions the message's flat, unaggregated reactions
   * @param bool $isSaved whether the CURRENT member saved this message (L1.5)
   * @param string $currentMemberId the current reading member's identifier
   * @param array<string, string> $displayNames member display names, indexed by member identifier
   *
   * @return MessageOutput the message output
   */
  private function build(MessageView $view, array $attachments, array $reactions, bool $isSaved, string $currentMemberId, array $displayNames = []): MessageOutput
  {
    $isDeleted = null !== $view->deletedAt;

    $output = new MessageOutput();
    $output->id = $view->id;
    $output->conversation = '/api/conversations/' . $view->conversationId;
    $output->authorMember = '/api/organizations/' . $view->organizationId . '/members/' . $view->authorMemberId;
    $output->authorDisplayName = $displayNames[$view->authorMemberId] ?? null;
    $output->body = $isDeleted ? null : $view->body;
    $output->mentions = $isDeleted ? [] : array_map(
      fn (string $memberId): string => '/api/organizations/' . $view->organizationId . '/members/' . $memberId,
      $view->mentions,
    );
    $output->mentionNames = $isDeleted ? [] : array_filter(
      array_map(
        static fn (string $memberId): ?string => $displayNames[$memberId] ?? null,
        array_combine($view->mentions, $view->mentions),
      ),
      static fn (?string $name): bool => null !== $name,
    );
    $output->editedAt = $view->editedAt?->format('c');
    $output->isDeleted = $isDeleted;
    $output->deletedAt = $view->deletedAt?->format('c');
    $output->attachments = $isDeleted ? [] : array_map($this->attachmentMapper->fromAttachment(...), $attachments);
    $output->pinnedAt = $view->pinnedAt?->format('c');
    $output->pinnedBy = null !== $view->pinnedByMemberId
      ? '/api/organizations/' . $view->organizationId . '/members/' . $view->pinnedByMemberId
      : null;
    $output->reactions = $isDeleted ? [] : $this->aggregateReactions($reactions, $currentMemberId);
    // Deliberately NOT redacted on delete, unlike attachments/reactions: a
    // save is the CURRENT member's own private state (never another
    // member's content), and it must stay visible so the member can still
    // find and unsave a message even after it was later tombstoned —
    // mirrors `pinnedAt`/`pinnedBy` staying visible for the same reason.
    $output->isSaved = $isSaved;
    // Also deliberately NOT redacted on delete (L2.5): the replies
    // themselves are separate, still-readable messages, unlike the reactions/
    // attachments hidden above — see the class docblock.
    $output->replyCount = $view->replyCount;
    // Redacted on delete (B3): a reference is part of the message's own
    // authored content, mirroring attachments/reactions/mentions/body —
    // unlike replyCount/pinnedAt/isSaved, which are metadata ABOUT the
    // message rather than content the author wrote.
    $output->references = $isDeleted ? [] : $view->references;
    $output->createdAt = $view->createdAt->format('c');
    $output->updatedAt = $view->updatedAt->format('c');

    return $output;
  }

  /**
   * Method resolveDisplayNames.
   *
   * Resolves every member name a page of messages needs, in one call.
   *
   * Authors, mentions and pinning members are gathered together on purpose:
   * they come from the same directory, and asking per message — or worse, per
   * mention — is the N+1 the batch port exists to prevent.
   *
   * @since 1.1.0
   *
   * @param list<MessageView> $views the message views being mapped
   *
   * @return array<string, string> display names indexed by member identifier
   */
  private function resolveDisplayNames(array $views): array
  {
    if ([] === $views) {
      return [];
    }

    $memberIds = [];
    foreach ($views as $view) {
      $memberIds[] = [$view->authorMemberId];
      $memberIds[] = $view->mentions;

      if (null !== $view->pinnedByMemberId) {
        $memberIds[] = [$view->pinnedByMemberId];
      }
    }

    return $this->memberDirectory->displayNamesFor(
      $views[0]->organizationId,
      array_values(array_unique(array_merge(...$memberIds))),
    );
  }

  /**
   * Method groupAttachmentsByMessageId.
   *
   * @since 1.0.0
   *
   * @param list<MessagingAttachment> $attachments the flat attachment list
   *
   * @return array<string, list<MessagingAttachment>> the attachments indexed by owning message id
   */
  private function groupAttachmentsByMessageId(array $attachments): array
  {
    $grouped = [];
    foreach ($attachments as $attachment) {
      $grouped[$attachment->messageId()][] = $attachment;
    }

    return $grouped;
  }

  /**
   * Method groupReactionsByMessageId.
   *
   * @since 1.1.0
   *
   * @param list<MessageReactionView> $reactions the flat reaction list
   *
   * @return array<string, list<MessageReactionView>> the reactions indexed by owning message id
   */
  private function groupReactionsByMessageId(array $reactions): array
  {
    $grouped = [];
    foreach ($reactions as $reaction) {
      $grouped[$reaction->messageId][] = $reaction;
    }

    return $grouped;
  }

  /**
   * Method aggregateReactions.
   *
   * Aggregates a message's flat reaction list by emoji (`count`,
   * `reactedByMe` relative to `$currentMemberId`), ordered deterministically
   * by the emoji's own byte order (`ksort(..., SORT_STRING)`) — stable across
   * requests regardless of reaction insertion order.
   *
   * @since 1.1.0
   *
   * @param list<MessageReactionView> $reactions the message's flat reaction list
   * @param string $currentMemberId the current reading member's identifier
   *
   * @return list<array{emoji: string, count: int, reactedByMe: bool}> the aggregated reactions
   */
  private function aggregateReactions(array $reactions, string $currentMemberId): array
  {
    $countByEmoji = [];
    $reactedByMeByEmoji = [];

    foreach ($reactions as $reaction) {
      $countByEmoji[$reaction->emoji] = ($countByEmoji[$reaction->emoji] ?? 0) + 1;
      if ($reaction->memberId === $currentMemberId) {
        $reactedByMeByEmoji[$reaction->emoji] = true;
      }
    }

    ksort($countByEmoji, SORT_STRING);

    $aggregated = [];
    foreach ($countByEmoji as $emoji => $count) {
      $aggregated[] = [
        'emoji' => $emoji,
        'count' => $count,
        'reactedByMe' => $reactedByMeByEmoji[$emoji] ?? false,
      ];
    }

    return $aggregated;
  }
  // #endregion
}
