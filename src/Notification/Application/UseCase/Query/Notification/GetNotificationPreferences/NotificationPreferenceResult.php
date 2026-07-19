<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Notification\GetNotificationPreferences;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase NotificationPreferenceResult.
 *
 * A single customized (user, category) preference. Reused as the item type
 * both by the read use case ({@see GetNotificationPreferencesResult}) and by
 * the update use case, which echoes back the full customized set after the
 * upsert.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NotificationPreferenceResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $category the notification category
   * @param bool $emailEnabled whether email delivery is enabled for this category
   * @param bool $mercureEnabled whether Mercure delivery is enabled for this category
   * @param DateTimeImmutable $updatedAt the last update datetime
   */
  public function __construct(
    public string $category,
    public bool $emailEnabled,
    public bool $mercureEnabled,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
