<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Exception\InterventionNotificationDeliveryException;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\OrganizationNotificationPolicyPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\{OrganizationMemberId, OrganizationNotificationSettings};
use Shared\Application\Port\Outbound\{DurableEventContextPort, IdempotentConsumerPort};
use Throwable;

use function array_unique;
use function array_values;
use function in_array;
use function sprintf;

/**
 * Service InterventionNotificationService.
 *
 * @category Service
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
   * @param InterventionRecurrenceRecipientResolver $admins the organization administrator resolver
   */
  public function __construct(
    private NotificationPort $notifications,
    private OrganizationMemberRepositoryPort $members,
    private OrganizationNotificationPolicyPort $policy,
    private InterventionReviewerRecipientResolver $reviewers,
    private InterventionRecurrenceRecipientResolver $admins,
    private DurableEventContextPort $eventContext,
    private IdempotentConsumerPort $eventConsumer,
  ) {
  }

  /**
   * Method assigned.
   *
   * Sends the assigned member the intervention assignment notification.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param string $interventionName the intervention name value
   * @param string $memberId the member id value
   *
   * @return void no return value
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
   * Notifies the responsible member when review requests changes, and does nothing when no responsible member is set.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param string $interventionName the intervention name value
   * @param ?string $responsibleId the responsible id value
   *
   * @return void no return value
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
      $send = function () use ($memberId, $interventionId, $interventionName): void {
        $this->send(
          $memberId,
          'intervention.published',
          'Intervention published',
          sprintf('"%s" has been published.', $interventionName),
          $interventionId,
        );
      };
      $eventId = $this->eventContext->eventId();
      if (null === $eventId) {
        $send();
      } else {
        $this->eventConsumer->consume($eventId, 'intervention.published:' . $memberId, $send);
      }
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
          $this->deliver(new SendNotificationRequest(
            type: 'intervention.submitted',
            subject: 'Intervention submitted for review',
            body: sprintf('"%s" was submitted and awaits review.', $interventionName),
            channels: $channels,
            payload: ['interventionId' => $interventionId],
            recipientUserId: $reviewerUserId,
            organizationId: $organizationId,
          ));
        } catch (Throwable $exception) {
          if (null !== $this->eventContext->eventId()) {
            throw $exception;
          }
        }
      }
    } catch (Throwable $exception) {
      // A durable delivery can retry without affecting the committed publication.
      if (null !== $this->eventContext->eventId()) {
        throw $exception;
      }
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
      new InterventionReminderDelivery($interventionId, $interventionNumber, $interventionName, $organizationId, $dueAt, $memberIds),
    );
  }

  /**
   * Method overdue.
   *
   * Notifies the intervention's responsible member and participants that its
   * `dueAt` has passed, and escalates to the organization's administrators
   * (active members granted `organization.interventions.plan`, resolved by
   * {@see InterventionRecurrenceRecipientResolver}) — an overdue intervention
   * is a compliance signal the people who plan the work must see even when
   * they are neither responsible nor participant. Recipients are
   * deduplicated by user. See {@see self::dueSoon()} for the delivery rules.
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
      new InterventionReminderDelivery($interventionId, $interventionNumber, $interventionName, $organizationId, $dueAt, $memberIds),
      escalateToAdmins: true,
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

      $this->deliver(new SendNotificationRequest(
        type: 'intervention.comment_mention',
        subject: 'Mentioned in a comment',
        body: 'A teammate mentioned you in an intervention comment.',
        channels: $channels,
        payload: ['interventionId' => $interventionId],
        recipientUserId: $member->userId(),
        organizationId: $organizationId,
      ));
    } catch (Throwable $exception) {
      if (null !== $this->eventContext->eventId()) {
        throw $exception;
      }
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
   * @param InterventionReminderDelivery $delivery the intervention and candidate recipients
   * @param bool $escalateToAdmins whether to also notify the organization's administrators
   */
  private function remind(
    string $type,
    string $subject,
    InterventionReminderDelivery $delivery,
    bool $escalateToAdmins = false,
  ): void {
    try {
      $policy = $this->policy->notificationPolicy($delivery->organizationId);

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
        $delivery->interventionName,
        $delivery->interventionNumber,
        $delivery->dueAt->format('Y-m-d'),
        $delivery->organizationId,
        $delivery->interventionId,
      );

      $message = new SendNotificationRequest(
        type: $type,
        subject: $subject,
        body: $body,
        channels: $channels,
        payload: ['interventionId' => $delivery->interventionId],
        organizationId: $delivery->organizationId,
      );
      $notifiedUserIds = $this->sendReminderToMembers($delivery->organizationId, $delivery->memberIds, $message);

      if (!$escalateToAdmins) {
        return;
      }

      $this->sendReminderToAdministrators($delivery->organizationId, $notifiedUserIds, $message);
    } catch (Throwable) {
      // Notifications must not make a successful reminder sweep fail.
    }
  }

  /**
   * @param list<string> $memberIds
   *
   * @return list<string> user ids attempted as ordinary reminder recipients
   */
  private function sendReminderToMembers(string $organizationId, array $memberIds, SendNotificationRequest $message): array
  {
    $notifiedUserIds = [];
    foreach (array_values(array_unique($memberIds)) as $memberId) {
      $member = $this->members->findById(OrganizationMemberId::fromString($memberId));
      if (null === $member || !$member->isActive() || (string) $member->organizationId() !== $organizationId) {
        continue;
      }

      $notifiedUserIds[] = $member->userId();
      $this->sendReminderToUser($member->userId(), $message);
    }

    return $notifiedUserIds;
  }

  /**
   * @param list<string> $notifiedUserIds
   */
  private function sendReminderToAdministrators(string $organizationId, array $notifiedUserIds, SendNotificationRequest $message): void
  {
    // Escalation excludes anyone already notified as responsible or participant.
    foreach ($this->admins->organizationAdministrators($organizationId) as $adminUserId) {
      if (in_array($adminUserId, $notifiedUserIds, true)) {
        continue;
      }

      $this->sendReminderToUser($adminUserId, $message);
    }
  }

  /**
   * Method sendReminderToUser.
   *
   * Sends one reminder and contains delivery failures so other recipients can still be notified.
   *
   * @access private
   *
   * @param string $userId the recipient user identifier
   * @param SendNotificationRequest $message the reminder content and delivery context
   *
   * @return void no return value
   */
  private function sendReminderToUser(string $userId, SendNotificationRequest $message): void
  {
    try {
      $this->deliver(new SendNotificationRequest(
        type: $message->type,
        subject: $message->subject,
        body: $message->body,
        channels: $message->channels,
        payload: $message->payload,
        recipientUserId: $userId,
        organizationId: $message->organizationId,
      ));
    } catch (Throwable) {
      // Best-effort per recipient: one failed delivery must not starve the others.
    }
  }

  /**
   * Method send.
   *
   * Delivers an intervention notification only for an active organization member whose notification policy allows it.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $memberId the member id value
   * @param string $type the type value
   * @param string $subject the subject value
   * @param string $body the body value
   * @param string $interventionId the intervention id value
   *
   * @return void no return value
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

      $this->deliver(new SendNotificationRequest(
        type: $type,
        subject: $subject,
        body: $body,
        channels: [NotificationChannel::MERCURE],
        payload: ['interventionId' => $interventionId],
        recipientUserId: $member->userId(),
        organizationId: (string) $member->organizationId(),
      ));
    } catch (Throwable $exception) {
      if (null !== $this->eventContext->eventId()) {
        throw $exception;
      }
    }
  }

  /**
   * Method deliver
   *
   * Uses the outbox event and recipient identity to retain channel acknowledgements across retry.
   *
   * @access private
   *
   * @param SendNotificationRequest $request one recipient's notification
   *
   * @return void
   */
  private function deliver(SendNotificationRequest $request): void
  {
    $eventId = $this->eventContext->eventId();
    $sent = $this->notifications->send(new SendNotificationRequest(
      type: $request->type,
      subject: $request->subject,
      body: $request->body,
      channels: $request->channels,
      payload: $request->payload,
      recipientUserId: $request->recipientUserId,
      recipientEmail: $request->recipientEmail,
      organizationId: $request->organizationId,
      idempotencyKey: null === $eventId ? null : $eventId . ':' . $request->type . ':' . ($request->recipientUserId ?? $request->recipientEmail ?? ''),
    ));
    if (null !== $eventId && in_array('failed', $sent->channelStatus, true)) {
      throw new InterventionNotificationDeliveryException('Intervention notification has a failed channel; durable delivery will retry.');
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
