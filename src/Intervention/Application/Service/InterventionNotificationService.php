<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

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
   */
  public function __construct(
    private NotificationPort $notifications,
    private OrganizationMemberRepositoryPort $members,
    private OrganizationNotificationPolicyPort $policy,
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
