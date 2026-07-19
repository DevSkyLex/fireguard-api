<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Notification\GetNotificationPreferences;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetNotificationPreferencesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetNotificationPreferencesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<NotificationPreferenceResult> $preferences the customized preferences; categories with no entry are enabled on every channel
   */
  public function __construct(
    public array $preferences,
  ) {
  }
  // #endregion
}
