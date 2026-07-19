<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Command\Notification\UpdateNotificationPreferences;

use Notification\Application\Port\Outbound\NotificationPreferenceRepositoryPort;
use Notification\Application\UseCase\Query\Notification\GetNotificationPreferences\NotificationPreferenceResult;
use Notification\Domain\Model\NotificationPreference\NotificationPreference;
use Shared\Application\Message\CommandHandler;

use function array_map;
use function array_values;

/**
 * UseCase UpdateNotificationPreferencesHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateNotificationPreferencesHandler implements CommandHandler
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
   * Upserts the authenticated user's per-category preferences, then returns
   * the full customized set. Duplicate categories in the same request are
   * de-duplicated, the last entry winning.
   *
   * @since 1.0.0
   *
   * @param UpdateNotificationPreferencesCommand $command the command payload
   *
   * @return UpdateNotificationPreferencesResult the use case result
   */
  public function __invoke(UpdateNotificationPreferencesCommand $command): UpdateNotificationPreferencesResult
  {
    /** @var array<string, array{category: string, emailEnabled: bool, mercureEnabled: bool}> $byCategory */
    $byCategory = [];
    foreach ($command->preferences as $entry) {
      $byCategory[$entry['category']] = $entry;
    }

    $preferences = array_map(
      static fn (array $entry): NotificationPreference => NotificationPreference::create(
        userId: $command->userId,
        category: $entry['category'],
        emailEnabled: $entry['emailEnabled'],
        mercureEnabled: $entry['mercureEnabled'],
      ),
      array_values($byCategory),
    );

    $this->preferenceRepository->saveMany($preferences);

    $current = $this->preferenceRepository->findByUserId($command->userId);

    return new UpdateNotificationPreferencesResult(
      preferences: array_map(
        static fn (NotificationPreference $preference): NotificationPreferenceResult => new NotificationPreferenceResult(
          category: $preference->category(),
          emailEnabled: $preference->isEmailEnabled(),
          mercureEnabled: $preference->isMercureEnabled(),
          updatedAt: $preference->updatedAt(),
        ),
        $current,
      ),
    );
  }
  // #endregion
}
