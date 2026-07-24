<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO ChannelOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ChannelOutput
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
   * Property name.
   *
   * @since 1.0.0
   */
  public string $name = '';

  /**
   * Property team.
   *
   * The bound organization team's IRI. Null when the channel is not
   * (or no longer) bound to a team.
   *
   * @since 1.0.0
   */
  public ?string $team = null;

  /**
   * Property createdByMember.
   *
   * The creating member's organization-member IRI.
   *
   * @since 1.0.0
   */
  public ?string $createdByMember = null;

  /**
   * Property participantCount.
   *
   * @since 1.0.0
   */
  public int $participantCount = 0;

  /**
   * Property isArchived.
   *
   * @since 1.0.0
   */
  public bool $isArchived = false;

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
   * Property unreadCount.
   *
   * The acting member's unread message count for this channel.
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
   * Property isFavorite.
   *
   * Whether the CURRENT member favorited this channel. Purely SIDEBAR
   * ORDERING — private to that member, grants no access, and MUST NEVER be
   * consulted when authorizing a read (see
   * `MessagingConversationFavoriteRepositoryPort`).
   *
   * @since 1.2.0
   */
  public bool $isFavorite = false;

  /**
   * Property parent.
   *
   * The parent channel's IRI, if this channel is nested under another
   * (L2.6). Null for a top-level channel. Exposed on every channel returned
   * by `GET /api/channels` (and `GET /api/channels/{id}`) so a client can
   * reconstruct the full hierarchy tree from the flat list, without a
   * separate request per channel.
   *
   * @since 1.3.0
   */
  public ?string $parent = null;
}
