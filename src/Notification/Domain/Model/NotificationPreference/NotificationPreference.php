<?php

declare(strict_types=1);

namespace Notification\Domain\Model\NotificationPreference;

use DateTimeImmutable;

/**
 * Model NotificationPreference.
 *
 * A user's per-category delivery preference, composite-keyed (`userId`,
 * `category`). Only categories the user actually customized exist as an
 * instance of this model — the absence of one means "every channel enabled"
 * and must never be materialized/backfilled.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationPreference
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $category the notification category (the `{category}` half of a `{category}.{action}` type)
   * @param bool $emailEnabled whether email delivery is enabled for this category
   * @param bool $mercureEnabled whether Mercure (real-time) delivery is enabled for this category
   * @param DateTimeImmutable $updatedAt the last update datetime
   */
  private function __construct(
    private string $userId,
    private string $category,
    private bool $emailEnabled,
    private bool $mercureEnabled,
    private DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new notification preference, used to upsert an explicit
   * customization for a (user, category) pair.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $category the notification category
   * @param bool $emailEnabled whether email delivery is enabled for this category
   * @param bool $mercureEnabled whether Mercure delivery is enabled for this category
   *
   * @return self the created preference
   */
  public static function create(
    string $userId,
    string $category,
    bool $emailEnabled = true,
    bool $mercureEnabled = true,
  ): self {
    return new self(
      userId: $userId,
      category: $category,
      emailEnabled: $emailEnabled,
      mercureEnabled: $mercureEnabled,
      updatedAt: new DateTimeImmutable(),
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes a notification preference from persistence.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $category the notification category
   * @param bool $emailEnabled whether email delivery is enabled for this category
   * @param bool $mercureEnabled whether Mercure delivery is enabled for this category
   * @param DateTimeImmutable $updatedAt the last update datetime
   *
   * @return self the reconstituted preference
   */
  public static function reconstitute(
    string $userId,
    string $category,
    bool $emailEnabled,
    bool $mercureEnabled,
    DateTimeImmutable $updatedAt,
  ): self {
    return new self(
      userId: $userId,
      category: $category,
      emailEnabled: $emailEnabled,
      mercureEnabled: $mercureEnabled,
      updatedAt: $updatedAt,
    );
  }

  /**
   * Method userId.
   *
   * @since 1.0.0
   */
  public function userId(): string
  {
    return $this->userId;
  }

  /**
   * Method category.
   *
   * @since 1.0.0
   */
  public function category(): string
  {
    return $this->category;
  }

  /**
   * Method isEmailEnabled.
   *
   * @since 1.0.0
   */
  public function isEmailEnabled(): bool
  {
    return $this->emailEnabled;
  }

  /**
   * Method isMercureEnabled.
   *
   * @since 1.0.0
   */
  public function isMercureEnabled(): bool
  {
    return $this->mercureEnabled;
  }

  /**
   * Method updatedAt.
   *
   * @since 1.0.0
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }
  // #endregion
}
