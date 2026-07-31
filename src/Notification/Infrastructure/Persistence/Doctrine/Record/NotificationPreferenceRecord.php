<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record NotificationPreferenceRecord.
 *
 * A user's per-category delivery preferences, composite-keyed (`user_id`,
 * `category`). Only categories the user actually customized are stored; an
 * absent row means "every channel enabled", so the table stays small and a new
 * notification category needs no backfill.
 *
 * `user_id` carries NO foreign key: notifications live on the `main` database
 * while `users` lives on `auth`, and a cross-database constraint is not
 * expressible — the same reason `notifications.recipient_user_id` is a plain
 * column.
 *
 * `category` is stored as a free string rather than an enum, matching
 * {@see \Notification\Application\Contract\Notification\NotificationType}'s deliberate
 * tolerance of unknown types: a new category must not require a deployment to
 * become preference-able.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'notification_preferences')]
#[ORM\Index(name: 'idx_notification_preference_user', columns: ['user_id'])]
class NotificationPreferenceRecord
{
  // #region Properties
  /**
   * Property userId.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  public string $userId;

  /**
   * Property category.
   *
   * The `{category}` half of a `{category}.{action}` notification type.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(name: 'category', type: 'string', length: 64)]
  public string $category;

  /**
   * Property emailEnabled.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'email_enabled', type: 'boolean', options: ['default' => true])]
  public bool $emailEnabled = true;

  /**
   * Property mercureEnabled.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'mercure_enabled', type: 'boolean', options: ['default' => true])]
  public bool $mercureEnabled = true;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
  // #endregion
}
