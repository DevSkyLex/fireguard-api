<?php

declare(strict_types=1);

namespace Messaging\Application\Service;

use Messaging\Application\Port\Outbound\MessagingMemberDirectoryPort;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\OrganizationNotificationPolicyPort;
use Throwable;

/**
 * Service MessagingNotificationService.
 *
 * Mention fan-out, cloned from
 * `Intervention\Application\Service\InterventionNotificationService::mentioned()`:
 * delivered in-app AND by email, each channel honoring its own organization
 * toggle. Best-effort — never fails the message post.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingNotificationService
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NotificationPort $notifications the notifications value
   * @param MessagingMemberDirectoryPort $members the messaging member directory port
   * @param OrganizationNotificationPolicyPort $policy the organization notification policy port
   */
  public function __construct(
    private NotificationPort $notifications,
    private MessagingMemberDirectoryPort $members,
    private OrganizationNotificationPolicyPort $policy,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method mentioned.
   *
   * Notifies a member that a teammate mentioned them in a conversation
   * message. The member id comes from user input (a parsed `@{uuid}`
   * token), so active membership in the conversation's organization is
   * verified here before anything is sent.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization owning the conversation
   * @param string $conversationId the conversation identifier
   * @param string $subjectType the conversation subject type
   * @param ?string $subjectId the conversation subject identifier, if any
   * @param string $mentionedMemberId the mentioned member identifier
   */
  public function mentioned(string $organizationId, string $conversationId, string $subjectType, ?string $subjectId, string $mentionedMemberId): void
  {
    try {
      if (!$this->members->memberIsActive($organizationId, $mentionedMemberId)) {
        return;
      }

      $recipientUserId = $this->members->resolveUserIdForMember($organizationId, $mentionedMemberId);
      if (null === $recipientUserId) {
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
        type: 'messaging.mention',
        subject: 'Mentioned in a discussion',
        body: 'A teammate mentioned you in a discussion.',
        channels: $channels,
        payload: [
          'conversationId' => $conversationId,
          'subjectType' => $subjectType,
          'subjectId' => $subjectId,
        ],
        recipientUserId: $recipientUserId,
        organizationId: $organizationId,
      ));
    } catch (Throwable) {
      // Notifications must not make a successful message post fail.
    }
  }

  /**
   * Method channelMessagePosted.
   *
   * Notifies every OTHER channel participant that a new message was posted.
   * Best-effort, synchronous, one notification send per recipient — cost
   * grows with participant count; acceptable for now, flagged as a future
   * async (Messenger) candidate for large channels. Never fails the post.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization owning the channel
   * @param string $conversationId the channel (conversation) identifier
   * @param list<string> $participantMemberIds every current participant's member identifier
   * @param string $authorMemberId the posting member's identifier, excluded from the fan-out
   */
  public function channelMessagePosted(string $organizationId, string $conversationId, array $participantMemberIds, string $authorMemberId): void
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

      foreach ($participantMemberIds as $memberId) {
        if ($memberId === $authorMemberId) {
          continue;
        }

        if (!$this->members->memberIsActive($organizationId, $memberId)) {
          continue;
        }

        $recipientUserId = $this->members->resolveUserIdForMember($organizationId, $memberId);
        if (null === $recipientUserId) {
          continue;
        }

        $this->notifications->send(new SendNotificationRequest(
          type: 'messaging.channel_message',
          subject: 'New channel message',
          body: 'A teammate posted a new message in a channel you belong to.',
          channels: $channels,
          payload: ['conversationId' => $conversationId],
          recipientUserId: $recipientUserId,
          organizationId: $organizationId,
        ));
      }
    } catch (Throwable) {
      // Notifications must not make a successful message post fail.
    }
  }
  // #endregion
}
