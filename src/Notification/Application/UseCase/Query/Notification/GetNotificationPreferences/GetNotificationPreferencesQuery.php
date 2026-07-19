<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Notification\GetNotificationPreferences;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetNotificationPreferencesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetNotificationPreferencesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   */
  public function __construct(
    public string $userId,
  ) {
  }
  // #endregion
}
