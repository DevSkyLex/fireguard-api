<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\OrganizationMemberId;
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

      $this->notifications->send(new SendNotificationRequest(
        type: $type,
        subject: $subject,
        body: $body,
        channels: [NotificationChannel::MERCURE],
        payload: ['interventionId' => $interventionId],
        recipientUserId: $member->userId(),
      ));
    } catch (Throwable) {
      // Notifications must not make a successful intervention mutation fail.
    }
  }
}
