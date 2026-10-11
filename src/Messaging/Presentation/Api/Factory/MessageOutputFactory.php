<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Factory;

use Messaging\Application\Contract\Attachment\MessagingAttachmentView;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Contract\Reaction\MessageReactionView;
use Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesResult;
use Messaging\Presentation\Api\Dto\Output\MessageOutput;

use function array_combine;
use function array_filter;
use function array_map;
use function in_array;
use function ksort;

use const SORT_STRING;

/**
 * Factory MessageOutputFactory.
 *
 * @category Factory
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessageOutputFactory
{
  // #region Constants
  /**
   * Constant ORGANIZATION_IRI_PREFIX
   *
   * @access private
   *
   * @var string
   */
  private const string ORGANIZATION_IRI_PREFIX = '/api/organizations/';

  /**
   * Constant MEMBER_IRI_SEGMENT
   *
   * @access private
   *
   * @var string
   */
  private const string MEMBER_IRI_SEGMENT = '/members/';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Maps already enriched message projections without reading persistence.
   *
   * @access public
   *
   * @param MessageAttachmentOutputFactory $attachmentMapper maps attachment outputs
   *
   * @return void
   */
  public function __construct(private readonly MessageAttachmentOutputFactory $attachmentMapper)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromView.
   *
   * Maps a single message using the same prepared projection as a whole page.
   *
   * @since 1.0.0
   *
   * @param MessageView $view the message view
   * @param string $currentMemberId the CURRENT (reading) member's identifier,
   *                                so `reactions[].reactedByMe` is computed
   *                                relative to THIS member and never leaks
   *                                another member's flag
   * @param EnrichMessagesResult $enrichment whole-page related metadata
   *
   * @return MessageOutput the message output
   */
  public function fromView(MessageView $view, string $currentMemberId, EnrichMessagesResult $enrichment): MessageOutput
  {
    return $this->fromViews([$view], $currentMemberId, $enrichment)[0];
  }

  /**
   * Method fromViews.
   *
   * Redacts `body`/`mentions` when the message is deleted (`deletedAt` set):
   * the persisted record retains them (compliance), but the API contract
   * MUST never expose a tombstoned message's content. Attachments and
   * reactions are redacted the same way — a tombstoned message's entire
   * social surface is hidden, not just its body. Related metadata is already
   * batched by Application, so this mapping performs no queries.
   *
   * @since 1.0.0
   *
   * @param list<MessageView> $views the message views
   * @param string $currentMemberId the CURRENT (reading) member's identifier,
   *                                so `reactions[].reactedByMe` is computed
   *                                relative to THIS member and never leaks
   *                                another member's flag
   * @param EnrichMessagesResult $enrichment whole-page related metadata
   *
   * @return list<MessageOutput> the message outputs
   */
  public function fromViews(array $views, string $currentMemberId, EnrichMessagesResult $enrichment): array
  {
    return array_map(
      fn (MessageView $view): MessageOutput => $this->build(
        $view,
        $enrichment->attachments[$view->id] ?? [],
        $enrichment->reactions[$view->id] ?? [],
        in_array($view->id, $enrichment->savedMessageIds, true),
        $currentMemberId,
        $enrichment->displayNames,
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
   * @param list<MessagingAttachmentView> $attachments the message's attachments
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
    $output->authorMember = self::ORGANIZATION_IRI_PREFIX . $view->organizationId . self::MEMBER_IRI_SEGMENT . $view->authorMemberId;
    $output->authorDisplayName = $displayNames[$view->authorMemberId] ?? null;
    $output->body = $isDeleted ? null : $view->body;
    $output->mentions = $isDeleted ? [] : array_map(
      fn (string $memberId): string => self::ORGANIZATION_IRI_PREFIX . $view->organizationId . self::MEMBER_IRI_SEGMENT . $memberId,
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
    $output->attachments = $isDeleted ? [] : array_map($this->attachmentMapper->fromView(...), $attachments);
    $output->pinnedAt = $view->pinnedAt?->format('c');
    $output->pinnedBy = null !== $view->pinnedByMemberId
      ? self::ORGANIZATION_IRI_PREFIX . $view->organizationId . self::MEMBER_IRI_SEGMENT . $view->pinnedByMemberId
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
