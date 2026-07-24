<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\OrganizationNotificationPolicyPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\OrganizationMemberId;
use Throwable;

/**
 * Service InterventionRecurrenceNotifier.
 *
 * Sends a best-effort `intervention.recurrence_failed` notification when the
 * materializer fails to produce an intervention for a due occurrence. The
 * recurrence's responsible member is notified when set and still an active
 * member of the organization; otherwise the organization's administrators
 * are notified as a fallback (mirroring
 * {@see \Maintenance\Application\Service\MaintenanceReminderNotifier}). A
 * notification failure must never fail the recurring sweep.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionRecurrenceNotifier
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NotificationPort $notifications the notification inbound port
   * @param OrganizationNotificationPolicyPort $policy the organization notification policy port
   * @param OrganizationMemberRepositoryPort $members the organization member repository port
   * @param InterventionRecurrenceRecipientResolver $recipients the recurrence recipient resolver
   */
  public function __construct(
    private NotificationPort $notifications,
    private OrganizationNotificationPolicyPort $policy,
    private OrganizationMemberRepositoryPort $members,
    private InterventionRecurrenceRecipientResolver $recipients,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method notifyMaterializationFailed.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $recurrenceId the recurrence identifier
   * @param string $templateId the recurrence's template identifier
   * @param ?string $responsibleId the recurrence's responsible member override, if any
   * @param string $reason the failure reason
   */
  public function notifyMaterializationFailed(
    string $organizationId,
    string $recurrenceId,
    string $templateId,
    ?string $responsibleId,
    string $reason,
  ): void {
    try {
      $policy = $this->policy->notificationPolicy($organizationId);

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

      $payload = [
        'organizationId' => $organizationId,
        'recurrenceId' => $recurrenceId,
        'templateId' => $templateId,
        'reason' => $reason,
      ];

      foreach ($this->resolveRecipients($organizationId, $responsibleId) as $userId) {
        try {
          $this->notifications->send(new SendNotificationRequest(
            type: 'intervention.recurrence_failed',
            subject: 'Recurring intervention could not be created',
            body: 'A recurring intervention could not be created from its template.',
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

  /**
   * Method resolveRecipients.
   *
   * Resolves the recurrence's responsible member (when still active) as the
   * sole recipient, falling back to the organization's administrators
   * otherwise.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param ?string $responsibleId the recurrence's responsible member override, if any
   *
   * @return list<string> the recipient user identifiers
   */
  private function resolveRecipients(string $organizationId, ?string $responsibleId): array
  {
    if (null !== $responsibleId) {
      $member = $this->members->findById(OrganizationMemberId::fromString($responsibleId));
      if (null !== $member && $member->isActive() && (string) $member->organizationId() === $organizationId) {
        return [$member->userId()];
      }
    }

    return $this->recipients->organizationAdministrators($organizationId);
  }
  // #endregion
}
