<?php

declare(strict_types=1);

namespace Mission\Application\Service;

use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\OrganizationMemberId;
use Throwable;

use function array_unique;
use function array_values;
use function sprintf;

/**
 * Service MissionNotificationService.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MissionNotificationService
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MissionNotificationService class.
   *
   * @since 1.0.0
   *
   * @param NotificationPort $notifications the notifications value
   * @param OrganizationMemberRepositoryPort $members the members value
   */
  public function __construct(
    private NotificationPort $notifications,
    private OrganizationMemberRepositoryPort $members,
  ) {
  }

  /**
   * Method assigned.
   *
   * Executes the assigned operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   * @param string $missionName the mission name value
   * @param string $memberId the member id value
   */
  public function assigned(string $missionId, string $missionName, string $memberId): void
  {
    $this->send(
      $memberId,
      'mission.assigned',
      'Mission assigned',
      sprintf('You have been assigned work in "%s".', $missionName),
      $missionId,
    );
  }

  /**
   * Method changesRequested.
   *
   * Executes the changes requested operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   * @param string $missionName the mission name value
   * @param ?string $responsibleId the responsible id value
   */
  public function changesRequested(string $missionId, string $missionName, ?string $responsibleId): void
  {
    if (null === $responsibleId) {
      return;
    }

    $this->send(
      $responsibleId,
      'mission.changes_requested',
      'Mission changes requested',
      sprintf('Corrections were requested for "%s".', $missionName),
      $missionId,
    );
  }

  /**
   * Method published.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   * @param string $missionName the mission name value
   * @param list<string> $memberIds
   */
  public function published(string $missionId, string $missionName, array $memberIds): void
  {
    foreach (array_values(array_unique($memberIds)) as $memberId) {
      $this->send(
        $memberId,
        'mission.published',
        'Mission published',
        sprintf('"%s" has been published.', $missionName),
        $missionId,
      );
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
   * @param string $missionId the mission id value
   */
  private function send(string $memberId, string $type, string $subject, string $body, string $missionId): void
  {
    try {
      $member = $this->members->findById(OrganizationMemberId::fromString($memberId));
      if (null === $member || !$member->isActive()) {
        return;
      }

      $this->notifications->send(new SendNotificationRequest(
        type: $type,
        subject: $subject,
        body: $body,
        channels: [NotificationChannel::MERCURE],
        payload: ['missionId' => $missionId],
        recipientUserId: $member->userId(),
      ));
    } catch (Throwable) {
      // Notifications must not make a successful mission mutation fail.
    }
  }
}
