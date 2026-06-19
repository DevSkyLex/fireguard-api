<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use function is_array;

/**
 * ValueObject OrganizationSettings.
 *
 * Root container for an organization's structured preferences. It is persisted
 * as a single JSON blob and groups the per-concern sub-sections (notifications,
 * regional). New sections are added as further nested value objects.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationSettings
{
  // #region Properties
  /**
   * Notification policy sub-section.
   *
   * @since 1.0.0
   */
  public OrganizationNotificationSettings $notifications;

  /**
   * Regional and formatting sub-section.
   *
   * @since 1.0.0
   */
  public OrganizationRegionalSettings $regional;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationSettings class.
   *
   * @since 1.0.0
   *
   * @param ?OrganizationNotificationSettings $notifications the notification sub-section
   * @param ?OrganizationRegionalSettings $regional the regional sub-section
   */
  public function __construct(
    ?OrganizationNotificationSettings $notifications = null,
    ?OrganizationRegionalSettings $regional = null,
  ) {
    $this->notifications = $notifications ?? new OrganizationNotificationSettings();
    $this->regional = $regional ?? new OrganizationRegionalSettings();
  }
  // #endregion

  // #region Methods
  /**
   * Method default.
   *
   * @static
   *
   * Returns an organization settings instance with all defaults applied.
   *
   * @since 1.0.0
   *
   * @return self the default settings instance
   */
  public static function default(): self
  {
    return new self();
  }

  /**
   * Method withNotifications.
   *
   * Returns a new instance with the notification sub-section replaced.
   *
   * @since 1.0.0
   *
   * @param OrganizationNotificationSettings $notifications the new notification settings
   *
   * @return self the new instance
   */
  public function withNotifications(OrganizationNotificationSettings $notifications): self
  {
    return new self(notifications: $notifications, regional: $this->regional);
  }

  /**
   * Method withRegional.
   *
   * Returns a new instance with the regional sub-section replaced.
   *
   * @since 1.0.0
   *
   * @param OrganizationRegionalSettings $regional the new regional settings
   *
   * @return self the new instance
   */
  public function withRegional(OrganizationRegionalSettings $regional): self
  {
    return new self(notifications: $this->notifications, regional: $regional);
  }

  /**
   * Method toArray.
   *
   * Returns the settings as a nested serializable array.
   *
   * @since 1.0.0
   *
   * @return array{notifications: array<string, bool>, regional: array<string, string>} the settings array
   */
  public function toArray(): array
  {
    return [
      'notifications' => $this->notifications->toArray(),
      'regional' => $this->regional->toArray(),
    ];
  }

  /**
   * Method fromArray.
   *
   * @static
   *
   * Creates settings from a (possibly empty) array, applying defaults for any
   * missing sub-section. Tolerates legacy empty `{}` rows.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the settings data
   *
   * @return self the settings instance
   */
  public static function fromArray(array $data): self
  {
    $notifications = $data['notifications'] ?? [];
    $regional = $data['regional'] ?? [];

    return new self(
      notifications: OrganizationNotificationSettings::fromArray(is_array($notifications) ? $notifications : []),
      regional: OrganizationRegionalSettings::fromArray(is_array($regional) ? $regional : []),
    );
  }
  // #endregion
}
