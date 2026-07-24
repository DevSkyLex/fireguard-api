<?php

declare(strict_types=1);

namespace Notification\Application\Port\Outbound;

use Notification\Domain\Model\NotificationPreference\NotificationPreference;

/**
 * Port NotificationPreferenceRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface NotificationPreferenceRepositoryPort
{
  // #region Methods
  /**
   * Method findByUserId.
   *
   * Returns every category the user explicitly customized. A category with
   * no returned row means "every channel enabled" — never backfilled.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   *
   * @return list<NotificationPreference> the customized preferences
   */
  public function findByUserId(string $userId): array;

  /**
   * Method findByUserIdAndCategory.
   *
   * Returns the customization for one (user, category) pair, or null when
   * absent — absence means "every channel enabled" for that category.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $category the notification category
   *
   * @return NotificationPreference|null the preference when customized
   */
  public function findByUserIdAndCategory(string $userId, string $category): ?NotificationPreference;

  /**
   * Method saveMany.
   *
   * Upserts a batch of preferences in a single flush.
   *
   * @since 1.0.0
   *
   * @param list<NotificationPreference> $preferences the preferences to upsert
   */
  public function saveMany(array $preferences): void;
  // #endregion
}
