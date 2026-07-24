<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Notification\GetNotificationPreferences;

use Notification\Application\Port\Outbound\NotificationPreferenceRepositoryPort;
use Notification\Domain\Model\NotificationPreference\NotificationPreference;
use Shared\Application\Message\QueryHandler;

use function array_map;

/**
 * UseCase GetNotificationPreferencesHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetNotificationPreferencesHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NotificationPreferenceRepositoryPort $preferenceRepository the preference repository port
   */
  public function __construct(
    private NotificationPreferenceRepositoryPort $preferenceRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Returns every category the authenticated user explicitly customized.
   * A category with no returned entry is enabled on every channel — absent
   * rows are never backfilled.
   *
   * @since 1.0.0
   *
   * @param GetNotificationPreferencesQuery $query the query payload
   *
   * @return GetNotificationPreferencesResult the use case result
   */
  public function __invoke(GetNotificationPreferencesQuery $query): GetNotificationPreferencesResult
  {
    $preferences = $this->preferenceRepository->findByUserId($query->userId);

    return new GetNotificationPreferencesResult(
      preferences: array_map(
        static fn (NotificationPreference $preference): NotificationPreferenceResult => self::mapPreference($preference),
        $preferences,
      ),
    );
  }

  /**
   * Method mapPreference.
   *
   * @since 1.0.0
   *
   * @param NotificationPreference $preference the domain preference
   *
   * @return NotificationPreferenceResult the mapped result item
   */
  private static function mapPreference(NotificationPreference $preference): NotificationPreferenceResult
  {
    return new NotificationPreferenceResult(
      category: $preference->category(),
      emailEnabled: $preference->isEmailEnabled(),
      mercureEnabled: $preference->isMercureEnabled(),
      updatedAt: $preference->updatedAt(),
    );
  }
  // #endregion
}
