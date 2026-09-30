<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

/** Event categories controlled by the organization notification policy. */
final readonly class OrganizationNotificationEvents
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Defines which notification event categories an organization enables.
   *
   * @access public
   *
   * @param bool $interventionPublished whether intervention publication notifications are enabled
   * @param bool $interventionAssigned whether intervention assignment notifications are enabled
   * @param bool $inspectionDue whether inspection due notifications are enabled
   * @param bool $nonConformityOpened whether non-conformity opened notifications are enabled
   * @param bool $nonConformitySlaBreached whether non-conformity SLA breach notifications are enabled
   * @param bool $memberInvited whether member invitation notifications are enabled
   * @param bool $weeklyDigest whether the weekly digest is enabled
   *
   * @return void
   */
  public function __construct(
    public bool $interventionPublished = true,
    public bool $interventionAssigned = true,
    public bool $inspectionDue = true,
    public bool $nonConformityOpened = true,
    public bool $nonConformitySlaBreached = true,
    public bool $memberInvited = true,
    public bool $weeklyDigest = true,
  ) {
  }
  // #endregion
}
