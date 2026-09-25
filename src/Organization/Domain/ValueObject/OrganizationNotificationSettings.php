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
   * Whether a non-conformity breaching its resolution SLA generates a
   * notification.
   *
   * @since 1.1.0
   */
  public bool $nonConformitySlaBreached;

  /**
   * Whether inviting a new member generates a notification.
   *
   * @since 1.0.0
   */
  public bool $memberInvited;

  /**
   * Whether the weekly operational digest email (overdue interventions,
   * maintenance deadlines, open non-conformities) is sent to the
   * organization's administrators.
   *
   * @since 1.2.0
   */
  public bool $weeklyDigest;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationNotificationSettings class.
   *
   * @since 1.0.0
   *
   * @param ?OrganizationNotificationChannels $channels enabled delivery channels
   * @param ?OrganizationNotificationEvents $events enabled event categories
   */
  public function __construct(
    ?OrganizationNotificationChannels $channels = null,
    ?OrganizationNotificationEvents $events = null,
  ) {
    $channels ??= new OrganizationNotificationChannels();
    $events ??= new OrganizationNotificationEvents();
    $this->emailEnabled = $channels->emailEnabled;
    $this->inAppEnabled = $channels->inAppEnabled;
    $this->interventionPublished = $events->interventionPublished;
    $this->interventionAssigned = $events->interventionAssigned;
    $this->inspectionDue = $events->inspectionDue;
    $this->nonConformityOpened = $events->nonConformityOpened;
    $this->nonConformitySlaBreached = $events->nonConformitySlaBreached;
    $this->memberInvited = $events->memberInvited;
    $this->weeklyDigest = $events->weeklyDigest;
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
      'non_conformity_sla_breached' => $this->nonConformitySlaBreached,
      'member_invited' => $this->memberInvited,
      'weekly_digest' => $this->weeklyDigest,
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
      channels: new OrganizationNotificationChannels(
        emailEnabled: (bool) ($data['email_enabled'] ?? true),
        inAppEnabled: (bool) ($data['in_app_enabled'] ?? true),
      ),
      events: new OrganizationNotificationEvents(
        interventionPublished: (bool) ($data['intervention_published'] ?? true),
        interventionAssigned: (bool) ($data['intervention_assigned'] ?? true),
        inspectionDue: (bool) ($data['inspection_due'] ?? true),
        nonConformityOpened: (bool) ($data['non_conformity_opened'] ?? true),
        nonConformitySlaBreached: (bool) ($data['non_conformity_sla_breached'] ?? true),
        memberInvited: (bool) ($data['member_invited'] ?? true),
        weeklyDigest: (bool) ($data['weekly_digest'] ?? true),
      ),
    );
  }
  // #endregion
}
