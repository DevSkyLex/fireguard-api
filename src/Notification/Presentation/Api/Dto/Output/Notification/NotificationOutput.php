<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Dto\Output\Notification;

use ApiPlatform\Metadata\ApiProperty;
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO NotificationOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property type.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $type = '';

  /**
   * Property category.
   *
   * The category segment extracted from the type string (prefix before
   * the first dot). Examples: `organization`, `system`.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $category = '';

  /**
   * Property subject.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $subject = '';

  /**
   * Property body.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $body = '';

  /**
   * @var list<string>
   */
  #[Groups([NotificationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $channels = [];

  /**
   * @var array<string, mixed>
   */
  #[Groups([NotificationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $payload = [];

  /**
   * Property isRead.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $isRead = false;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  /**
   * Property readAt.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $readAt = null;
  // #endregion
}
