<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\OrganizationNotificationPolicyPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\{OrganizationMemberId, OrganizationNotificationSettings};
use Throwable;

use function array_unique;
use function array_values;
use function sprintf;

/**
 * Service InterventionNotificationService.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionNotificationService
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionNotificationService class.
   *
   * @since 1.0.0
   *
   * @param NotificationPort $notifications the notifications value
   * @param OrganizationMemberRepositoryPort $members the members value
   * @param OrganizationNotificationPolicyPort $policy the organization notification policy port
   * @param InterventionReviewerRecipientResolver $reviewers the submission reviewer resolver
   */
  public function __construct(
    private NotificationPort $notifications,
    private OrganizationMemberRepositoryPort $members,
    private OrganizationNotificationPolicyPort $policy,
    private InterventionReviewerRecipientResolver $reviewers,
  ) {
  }

  /**
   * Method assigned.
   *
   * Executes the assigned operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param string $interventionName the intervention name value
   * @param string $memberId the member id value
   */
  public function assigned(string $interventionId, string $interventionName, string $memberId): void
  {
    $this->send(
      $memberId,
      'intervention.assigned',
      'Intervention assigned',
      sprintf('You have been assigned work in "%s".', $interventionName),
      $interventionId,
    );
  }

  /**
   * Method changesRequested.
   *
   * Executes the changes requested operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param string $interventionName the intervention name value
   * @param ?string $responsibleId the responsible id value
   */
  public function changesRequested(string $interventionId, string $interventionName, ?string $responsibleId): void
  {
    if (null === $responsibleId) {
      return;
    }

    $this->send(
      $responsibleId,
      'intervention.changes_requested',
      'Intervention changes requested',
      sprintf('Corrections were requested for "%s".', $interventionName),
      $interventionId,
    );
  }

  /**
   * Method published.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param string $interventionName the intervention name value
   * @param list<string> $memberIds
   */
  public function published(string $interventionId, string $interventionName, array $memberIds): void
  {
    foreach (array_values(array_unique($memberIds)) as $memberId) {
      $this->send(
        $memberId,
        'intervention.published',
        'Intervention published',
        sprintf('"%s" has been published.', $interventionName),
        $interventionId,
      );
    }
  }

  /**
   * Method submitted.
   *
   * Notifies the organization's reviewers — active members whose effective
   * permissions grant `organization.interventions.review` — that an
   * intervention awaits their review. A submission is workflow-critical, so
   * like a mention it is delivered in-app AND by email, each channel honoring
   * its own organization toggle. The submitting user is excluded. Every
   * resubmission notifies again: there is deliberately no deduplication, the
   * reviewers must learn about each new review round.
   *
   * @since 1.2.0
   *
   * @param string $interventionId the intervention id value
   * @param string $interventionName the intervention name value
   * @param string $organizationId the organization owning the intervention
   * @param string $actorUserId the submitting user, excluded from recipients
   */
  public function submitted(string $interventionId, string $interventionName, string $organizationId, string $actorUserId): void
  {
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

      foreach ($this->reviewers->organizationReviewers($organizationId) as $reviewerUserId) {
        if ($reviewerUserId === $actorUserId) {
          continue;
        }

        try {
          $this->notifications->send(new SendNotificationRequest(
            type: 'intervention.submitted',
            subject: 'Intervention submitted for review',
            body: sprintf('"%s" was submitted and awaits review.', $interventionName),
            channels: $channels,
            payload: ['interventionId' => $interventionId],
            recipientUserId: $reviewerUserId,
            organizationId: $organizationId,
          ));
        } catch (Throwable) {
          // Best-effort per recipient: one failed delivery must not starve the others.
        }
      }
    } catch (Throwable) {
      // Notifications must not make a successful intervention mutation fail.
    }
  }

  /**
   * Method dueSoon.
   *
   * Notifies the intervention's responsible member and participants that its
   * `dueAt` is within the reminder window. Workflow-critical like a
   * submission, so delivered in-app AND by email, each channel honoring its
   * own organization toggle. The caller ({@see
   * \Intervention\Application\UseCase\Command\Sweep\SendDueReminders\SendDueRemindersHandler})
   * is responsible for the anti-spam stamp — this method sends unconditionally.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param int $interventionNumber the intervention's human-readable number
   * @param string $interventionName the intervention name value
   * @param string $organizationId the organization owning the intervention
   * @param DateTimeImmutable $dueAt the intervention's due date
   * @param list<string> $memberIds the responsible and participant member ids
   */
  public function dueSoon(
    string $interventionId,
    int $interventionNumber,
    string $interventionName,
    string $organizationId,
    DateTimeImmutable $dueAt,
    array $memberIds,
  ): void {
    $this->remind(
      'intervention.due_soon',
      'Intervention due soon',
      $interventionId,
      $interventionNumber,
      $interventionName,
      $organizationId,
      $dueAt,
      $memberIds,
    );
  }

  /**
   * Method overdue.
   *
   * Notifies the intervention's responsible member and participants that its
   * `dueAt` has passed. See {@see self::dueSoon()} for the delivery rules.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param int $interventionNumber the intervention's human-readable number
   * @param string $interventionName the intervention name value
   * @param string $organizationId the organization owning the intervention
   * @param DateTimeImmutable $dueAt the intervention's due date
   * @param list<string> $memberIds the responsible and participant member ids
   */
  public function overdue(
    string $interventionId,
    int $interventionNumber,
    string $interventionName,
    string $organizationId,
    DateTimeImmutable $dueAt,
    array $memberIds,
  ): void {
    $this->remind(
      'intervention.overdue',
      'Intervention overdue',
      $interventionId,
      $interventionNumber,
      $interventionName,
      $organizationId,
      $dueAt,
      $memberIds,
    );
  }

  /**
   * Method mentioned.
   *
   * Notifies a member that a teammate mentioned them in an intervention
   * comment. Unlike workflow notifications, a mention is a direct address:
   * it is delivered in-app AND by email, each channel honoring its own
   * organization toggle. The member id comes from user input, so membership
   * in the intervention's organization is verified here.
   *
   * @since 1.1.0
   *
   * @param string $interventionId the intervention id value
   * @param string $organizationId the organization owning the intervention
   * @param string $memberId the mentioned member id value
   */
  public function mentioned(string $interventionId, string $organizationId, string $memberId): void
  {
    try {
      $member = $this->members->findById(OrganizationMemberId::fromString($memberId));
      if (null === $member || !$member->isActive() || (string) $member->organizationId() !== $organizationId) {
        return;
      }

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

      $this->notifications->send(new SendNotificationRequest(
        type: 'intervention.comment_mention',
        subject: 'Mentioned in a comment',
        body: 'A teammate mentioned you in an intervention comment.',
        channels: $channels,
        payload: ['interventionId' => $interventionId],
        recipientUserId: $member->userId(),
        organizationId: $organizationId,
      ));
    } catch (Throwable) {
      // Notifications must not make a successful intervention mutation fail.
    }
  }

  /**
   * Method remind.
   *
   * Shared delivery for the due-date reminders: resolves each candidate
   * member id to an active, in-organization member — mirroring how
   * {@see self::mentioned()} validates a member id sourced outside the
   * request that owns it — then sends best-effort to each resolved user.
   *
   * @since 1.0.0
   *
   * @param string $type the notification type value
   * @param string $subject the notification subject value
   * @param string $interventionId the intervention id value
   * @param int $interventionNumber the intervention's human-readable number
   * @param string $interventionName the intervention name value
   * @param string $organizationId the organization owning the intervention
   * @param DateTimeImmutable $dueAt the intervention's due date
   * @param list<string> $memberIds the candidate member ids
   */
  private function remind(
    string $type,
    string $subject,
    string $interventionId,
    int $interventionNumber,
    string $interventionName,
    string $organizationId,
    DateTimeImmutable $dueAt,
    array $memberIds,
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

      $body = sprintf(
        '"%s" (FG-%d) is due %s. /organizations/%s/interventions/%s',
        $interventionName,
        $interventionNumber,
        $dueAt->format('Y-m-d'),
        $organizationId,
        $interventionId,
      );

      foreach (array_values(array_unique($memberIds)) as $memberId) {
        $member = $this->members->findById(OrganizationMemberId::fromString($memberId));
        if (null === $member || !$member->isActive() || (string) $member->organizationId() !== $organizationId) {
          continue;
        }

        try {
          $this->notifications->send(new SendNotificationRequest(
            type: $type,
            subject: $subject,
            body: $body,
            channels: $channels,
            payload: ['interventionId' => $interventionId],
            recipientUserId: $member->userId(),
            organizationId: $organizationId,
          ));
        } catch (Throwable) {
          // Best-effort per recipient: one failed delivery must not starve the others.
        }
      }
    } catch (Throwable) {
      // Notifications must not make a successful reminder sweep fail.
    }
  }

  /**
   * Method send.
   *
   * Executes the send operation.
   *
   * @since 1.0.0
   *
   * @param string $memberId the member id value
   * @param string $type the type value
   * @param string $subject the subject value
   * @param string $body the body value
   * @param string $interventionId the intervention id value
   */
  private function send(string $memberId, string $type, string $subject, string $body, string $interventionId): void
  {
    try {
      $member = $this->members->findById(OrganizationMemberId::fromString($memberId));
      if (null === $member || !$member->isActive()) {
        return;
      }

      $policy = $this->policy->notificationPolicy((string) $member->organizationId());

      // Respect the organization policy: skip the event category when disabled,
      // and the real-time channel when in-app delivery is turned off.
      if (!$this->isCategoryEnabled($policy, $type) || !$policy->inAppEnabled) {
        return;
      }

      $this->notifications->send(new SendNotificationRequest(
        type: $type,
        subject: $subject,
        body: $body,
        channels: [NotificationChannel::MERCURE],
        payload: ['interventionId' => $interventionId],
        recipientUserId: $member->userId(),
        organizationId: (string) $member->organizationId(),
      ));
    } catch (Throwable) {
      // Notifications must not make a successful intervention mutation fail.
    }
  }

  /**
   * Method isCategoryEnabled.
   *
   * Maps an intervention notification type to its organization policy flag.
   * Workflow-critical events with no dedicated toggle (such as requested
   * changes) are always allowed.
   *
   * @since 1.0.0
   *
   * @param OrganizationNotificationSettings $policy the organization notification policy
   * @param string $type the notification type
   *
   * @return bool true when the category may notify
   */
  private function isCategoryEnabled(OrganizationNotificationSettings $policy, string $type): bool
  {
    return match ($type) {
      'intervention.assigned' => $policy->interventionAssigned,
      'intervention.published' => $policy->interventionPublished,
      default => true,
    };
  }
}
