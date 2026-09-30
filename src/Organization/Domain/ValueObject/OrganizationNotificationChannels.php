<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

/** Delivery channels for organization notifications. */
final readonly class OrganizationNotificationChannels
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Defines the enabled delivery channels for organization notifications.
   *
   * @access public
   *
   * @param bool $emailEnabled whether email delivery is enabled
   * @param bool $inAppEnabled whether in-app delivery is enabled
   *
   * @return void
   */
  public function __construct(
    public bool $emailEnabled = true,
    public bool $inAppEnabled = true,
  ) {
  }
  // #endregion
}
