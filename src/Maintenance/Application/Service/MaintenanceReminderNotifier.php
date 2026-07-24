<?php

declare(strict_types=1);

namespace Maintenance\Application\Service;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\OrganizationNotificationPolicyPort;
use Throwable;

/**
 * Service MaintenanceReminderNotifier.
 *
 * Sends a due/overdue inspection reminder to an organization's
 * administrators, honoring the `inspectionDue` category toggle and the
 * `inAppEnabled`/`emailEnabled` channel toggles — mirrors
 * `InterventionNotificationService::send()`/`mentioned()`. Best-effort: a
 * notification failure must never fail the recurring sweep.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceReminderNotifier
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NotificationPort $notifications the notification inbound port
   * @param OrganizationNotificationPolicyPort $policy the organization notification policy port
   * @param MaintenanceReminderRecipientResolver $recipients the reminder recipient resolver
   */
  public function __construct(
    private NotificationPort $notifications,
    private OrganizationNotificationPolicyPort $policy,
    private MaintenanceReminderRecipientResolver $recipients,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method remind.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $equipmentId the equipment identifier
   * @param ?string $facilityId the equipment's facility identifier, if any
   * @param DateTimeImmutable $nextDueAt the schedule's next due date
   * @param bool $overdue whether the schedule is overdue (vs. due soon)
   */
  public function remind(string $organizationId, string $equipmentId, ?string $facilityId, DateTimeImmutable $nextDueAt, bool $overdue): void
  {
    try {
      $policy = $this->policy->notificationPolicy($organizationId);
      if (!$policy->inspectionDue) {
        return;
      }

      $channels = [];
      if ($policy->inAppEnabled) {
        $channels[] = NotificationChannel::MERCURE;
      }
      if ($policy->emailEnabled) {
        $channels[] = NotificationChannel::EMAIL;
      }
      if ([] === $channels) {
        return;
      }

      $type = $overdue ? 'maintenance.inspection_overdue' : 'maintenance.inspection_due';
      $subject = $overdue ? 'Inspection overdue' : 'Inspection due soon';
      $body = $overdue
        ? 'An inspection is overdue for a piece of equipment.'
        : 'An inspection is due soon for a piece of equipment.';
      $payload = [
        'equipmentId' => $equipmentId,
        'facilityId' => $facilityId,
        'organizationId' => $organizationId,
        'nextDueAt' => $nextDueAt->format('c'),
      ];

      foreach ($this->recipients->organizationAdministrators($organizationId) as $userId) {
        try {
          $this->notifications->send(new SendNotificationRequest(
            type: $type,
            subject: $subject,
            body: $body,
            channels: $channels,
            payload: $payload,
            recipientUserId: $userId,
            organizationId: $organizationId,
          ));
        } catch (Throwable) {
          // Best-effort per recipient: one failed delivery must not skip the rest.
        }
      }
    } catch (Throwable) {
      // Notifications must never fail the recurring sweep.
    }
  }
  // #endregion
}
