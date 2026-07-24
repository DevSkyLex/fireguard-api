<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Command\Notification\UpdateNotificationPreferences;

use Notification\Application\UseCase\Query\Notification\GetNotificationPreferences\NotificationPreferenceResult;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdateNotificationPreferencesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateNotificationPreferencesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<NotificationPreferenceResult> $preferences the full customized preference set after the upsert
   */
  public function __construct(
    public array $preferences,
  ) {
  }
  // #endregion
}
