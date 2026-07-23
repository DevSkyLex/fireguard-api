<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO MessageOutput.
 *
 * When the message is deleted, `body` and `mentions` are redacted (`null`
 * and `[]`) even though the persisted record retains them — the tombstone
 * semantics MUST never leak through this contract.
 *
 * The `attachments`, `pinnedAt`, `pinnedBy`, `reactions`, `isSaved` and
 * `replyCount` properties are declared here ahead of the feature lots that
 * populate them, and until populated always serialize as their empty/neutral
 * value. That is deliberate: five otherwise-independent lots would each have
 * had to edit this class, making it the wave's worst merge hotspot. Declaring
 * the whole surface once confines each lot to `MessageOutputFactory`.
 * `attachments` (L1.2), `pinnedAt`/`pinnedBy` (L1.3), `reactions` (L1.4),
 * `isSaved` (L1.5) and `replyCount` (L2.5) are now all populated.
 * `replyCount` is deliberately NOT redacted when the message is tombstoned
 * (mirrors `pinnedAt`/`isSaved`): the reply messages themselves are separate,
 * still-readable content (`GET /messages/{id}/replies` never checks the
 * parent's tombstone state either), unlike `reactions`/`attachments`, which
 * ARE part of the deleted message's own social surface.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessageOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property conversation.
   *
   * @since 1.0.0
   */
  public string $conversation = '';

  /**
   * Property authorMember.
   *
   * The author's organization member IRI.
   *
   * @since 1.0.0
   */
  public string $authorMember = '';

  /**
   * Property authorDisplayName.
   *
   * The author's human-readable name.
   *
   * Carried on the message rather than left to the client: resolving it there
   * requires the member directory, which is gated behind
   * `organization.members.read` — a member without that permission was shown a
   * raw UUID above every message. `null` only when the name cannot be derived
   * at all, which the client renders as a neutral label, never as an id.
   *
   * @since 1.1.0
   */
  public ?string $authorDisplayName = null;

  /**
   * Property body.
   *
   * `null` when the message is deleted.
   *
   * @since 1.0.0
   */
  public ?string $body = null;

  /**
   * Property mentions.
   *
   * Mentioned organization member IRIs. Empty when the message is deleted.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public array $mentions = [];

  /**
   * Property mentionNames.
   *
   * Display names for the mentioned members, indexed by the BARE member
   * identifier — the same token the body carries as `@{memberUuid}`, so the
   * client can resolve a chip without parsing an IRI. Empty when the message
   * is deleted, and a member whose name cannot be derived is simply absent.
   *
   * @since 1.1.0
   *
   * @var array<string, string>
   */
  public array $mentionNames = [];

  /**
   * Property editedAt.
   *
   * @since 1.0.0
   */
  public ?string $editedAt = null;

  /**
   * Property isDeleted.
   *
   * @since 1.0.0
   */
  public bool $isDeleted = false;

  /**
   * Property deletedAt.
   *
   * @since 1.0.0
   */
  public ?string $deletedAt = null;

  /**
   * Property attachments.
   *
   * Files posted with this message. Populated by the attachments lot; `[]`
   * until then.
   *
   * @since 1.1.0
   *
   * @var list<MessageAttachmentOutput>
   */
  public array $attachments = [];

  /**
   * Property pinnedAt.
   *
   * Non-null when the message is pinned in its conversation. A pin is visible
   * to everyone who can read the conversation, unlike `isSaved`.
   *
   * @since 1.1.0
   */
  public ?string $pinnedAt = null;

  /**
   * Property pinnedBy.
   *
   * The pinning member's organization member IRI, when pinned.
   *
   * @since 1.1.0
   */
  public ?string $pinnedBy = null;

  /**
   * Property reactions.
   *
   * Emoji reactions aggregated by emoji. Populated by the reactions lot; `[]`
   * until then.
   *
   * @since 1.1.0
   *
   * @var list<array{emoji: string, count: int, reactedByMe: bool}>
   */
  public array $reactions = [];

  /**
   * Property isSaved.
   *
   * Whether the CURRENT member bookmarked this message. Private to that
   * member — never a property of the conversation.
   *
   * @since 1.1.0
   */
  public bool $isSaved = false;

  /**
   * Property replyCount.
   *
   * Number of threaded replies to this message (L2.5). Never redacted on
   * tombstone — see the class docblock.
   *
   * @since 1.1.0
   */
  public int $replyCount = 0;

  /**
   * Property references.
   *
   * Structured `{type, id, label, code}` rich-card references attached to
   * this message (B3), at most `MessageReference::MAX_REFERENCES` entries.
   * Redacted to `[]` when the message is deleted — a reference is part of
   * the message's own content, mirroring `attachments`/`reactions`, unlike
   * `pinnedAt`/`isSaved`/`replyCount` (metadata ABOUT the message, not
   * content the author wrote).
   *
   * @since 1.3.0
   *
   * @var list<array{type: string, id: string, label: ?string, code: ?string}>
   */
  public array $references = [];

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  public string $createdAt = '';

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  public string $updatedAt = '';
}
