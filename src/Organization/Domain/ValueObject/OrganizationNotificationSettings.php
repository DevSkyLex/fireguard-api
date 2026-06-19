<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

/**
 * ValueObject OrganizationNotificationSettings.
 *
 * Organization-wide notification policy: the delivery channels enabled for the
 * organization and the event categories that generate notifications. Every flag
 * is a simple boolean with a sensible "on" default.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationNotificationSettings
{
  // #region Properties
  /**
   * Whether notifications are delivered by email.
   *
   * @since 1.0.0
   */
  public bool $emailEnabled;

  /**
   * Whether notifications are delivered in-app (real-time).
   *
   * @since 1.0.0
   */
  public bool $inAppEnabled;

  /**
   * Whether publishing a field intervention generates a notification.
   *
   * @since 1.0.0
   */
  public bool $interventionPublished;

  /**
   * Whether assigning a field intervention generates a notification.
   *
   * @since 1.0.0
   */
  public bool $interventionAssigned;

  /**
   * Whether an upcoming/overdue inspection generates a notification.
   *
   * @since 1.0.0
   */
  public bool $inspectionDue;

  /**
   * Whether opening an equipment non-conformity generates a notification.
   *
   * @since 1.0.0
   */
  public bool $nonConformityOpened;

  /**
   * Whether inviting a new member generates a notification.
   *
   * @since 1.0.0
   */
  public bool $memberInvited;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationNotificationSettings class.
   *
   * @since 1.0.0
   *
   * @param bool $emailEnabled whether email delivery is enabled
   * @param bool $inAppEnabled whether in-app delivery is enabled
   * @param bool $interventionPublished whether intervention publication notifies
   * @param bool $interventionAssigned whether intervention assignment notifies
   * @param bool $inspectionDue whether due inspections notify
   * @param bool $nonConformityOpened whether opened non-conformities notify
   * @param bool $memberInvited whether member invitations notify
   */
  public function __construct(
    bool $emailEnabled = true,
    bool $inAppEnabled = true,
    bool $interventionPublished = true,
    bool $interventionAssigned = true,
    bool $inspectionDue = true,
    bool $nonConformityOpened = true,
    bool $memberInvited = true,
  ) {
    $this->emailEnabled = $emailEnabled;
    $this->inAppEnabled = $inAppEnabled;
    $this->interventionPublished = $interventionPublished;
    $this->interventionAssigned = $interventionAssigned;
    $this->inspectionDue = $inspectionDue;
    $this->nonConformityOpened = $nonConformityOpened;
    $this->memberInvited = $memberInvited;
  }
  // #endregion

  // #region Methods
  /**
   * Method toArray.
   *
   * Returns the notification settings as a serializable array.
   *
   * @since 1.0.0
   *
   * @return array<string, bool> the settings array
   */
  public function toArray(): array
  {
    return [
      'email_enabled' => $this->emailEnabled,
      'in_app_enabled' => $this->inAppEnabled,
      'intervention_published' => $this->interventionPublished,
      'intervention_assigned' => $this->interventionAssigned,
      'inspection_due' => $this->inspectionDue,
      'non_conformity_opened' => $this->nonConformityOpened,
      'member_invited' => $this->memberInvited,
    ];
  }

  /**
   * Method fromArray.
   *
   * @static
   *
   * Creates notification settings from a (possibly partial) array, falling back
   * to the "on" defaults for any missing flag.
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $data the settings data
   *
   * @return self the settings instance
   */
  public static function fromArray(array $data): self
  {
    return new self(
      emailEnabled: (bool) ($data['email_enabled'] ?? true),
      inAppEnabled: (bool) ($data['in_app_enabled'] ?? true),
      interventionPublished: (bool) ($data['intervention_published'] ?? true),
      interventionAssigned: (bool) ($data['intervention_assigned'] ?? true),
      inspectionDue: (bool) ($data['inspection_due'] ?? true),
      nonConformityOpened: (bool) ($data['non_conformity_opened'] ?? true),
      memberInvited: (bool) ($data['member_invited'] ?? true),
    );
  }
  // #endregion
}
