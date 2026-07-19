<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO ConversationOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ConversationOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property organization.
   *
   * @since 1.0.0
   */
  public string $organization = '';

  /**
   * Property subjectType.
   *
   * One of `facility`, `equipment`, `intervention`, `non_conformity`.
   *
   * @since 1.0.0
   */
  public string $subjectType = '';

  /**
   * Property subject.
   *
   * The subject's descriptive IRI, if any.
   *
   * @since 1.0.0
   */
  public ?string $subject = null;

  /**
   * Property subjectLabel.
   *
   * The subject's resolved display label. Only populated by `GetConversation`
   * (`ListConversations` skips per-row subject resolution for cost).
   *
   * @since 1.0.0
   */
  public ?string $subjectLabel = null;

  /**
   * Property visibility.
   *
   * @since 1.0.0
   */
  public string $visibility = '';

  /**
   * Property lastMessageAt.
   *
   * @since 1.0.0
   */
  public ?string $lastMessageAt = null;

  /**
   * Property messagesCount.
   *
   * @since 1.0.0
   */
  public int $messagesCount = 0;

  /**
   * Property isArchived.
   *
   * @since 1.0.0
   */
  public bool $isArchived = false;

  /**
   * Property unreadCount.
   *
   * The acting member's unread message count for this conversation.
   *
   * @since 1.0.0
   */
  public int $unreadCount = 0;

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

  /**
   * Property isChannel.
   *
   * `true` for a v2 channel (`subjectType=channel`), `false` for a v1
   * subject thread.
   *
   * @since 1.0.0
   */
  public bool $isChannel = false;

  /**
   * Property name.
   *
   * The channel display name. Null for a v1 subject thread.
   *
   * @since 1.0.0
   */
  public ?string $name = null;

  /**
   * Property team.
   *
   * The bound organization team's IRI, if any. Null for a v1 subject thread.
   *
   * @since 1.0.0
   */
  public ?string $team = null;

  /**
   * Property isFavorite.
   *
   * Whether the CURRENT member favorited this conversation. Purely SIDEBAR
   * ORDERING — private to that member, grants no access, and MUST NEVER be
   * consulted when authorizing a read (see
   * `MessagingConversationFavoriteRepositoryPort`).
   *
   * @since 1.2.0
   */
  public bool $isFavorite = false;
}
