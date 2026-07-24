<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Dto\Output\Inbox;

use ApiPlatform\Metadata\ApiProperty;
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO InboxItemOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InboxItemOutput
{
  // #region Properties
  /**
   * Property sourceKey.
   *
   * The owning source's key (e.g. `notification`).
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::INBOX_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $sourceKey = '';

  /**
   * Property id.
   *
   * Unique within its source, not necessarily unique across sources.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::INBOX_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $id = '';

  /**
   * Property kind.
   *
   * Short discriminator for the item's nature (e.g. `notification`,
   * `mention`, `direct_message`, `thread_reply`).
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::INBOX_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $kind = '';

  /**
   * Property title.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::INBOX_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $title = '';

  /**
   * Property snippet.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::INBOX_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $snippet = null;

  /**
   * Property occurredAt.
   *
   * ISO-8601 instant. Also the cursor value the client echoes back via
   * `before` to fetch the next page.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::INBOX_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $occurredAt = '';

  /**
   * Property isRead.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::INBOX_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $isRead = false;

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::INBOX_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $organizationId = null;

  /**
   * Property targetType.
   *
   * The type of entity the client should navigate to (e.g. `notification`,
   * `conversation`).
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::INBOX_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $targetType = '';

  /**
   * Property targetId.
   *
   * The identifier of the entity the client should navigate to.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::INBOX_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $targetId = '';
  // #endregion
}
