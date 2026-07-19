<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Command\Notification\UpdateNotificationPreferences;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateNotificationPreferencesCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateNotificationPreferencesCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param list<array{category: string, emailEnabled: bool, mercureEnabled: bool}> $preferences the preference entries to upsert
   */
  public function __construct(
    public string $userId,
    public array $preferences,
  ) {
  }
  // #endregion
}
