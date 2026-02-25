<?php

declare(strict_types=1);

namespace Onboarding\Infrastructure\EventSubscriber;

use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Onboarding\Domain\Event\OrganizationOnboardingSessionCompletedEvent;
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function sprintf;

/**
 * EventSubscriber OnboardingNotificationSubscriber.
 *
 * Reacts to {@see OrganizationOnboardingSessionCompletedEvent} and dispatches
 * a best-effort notification through the Notification module.
 * Two channels are attempted:
 *   - EMAIL   – requires a resolved user e-mail address
 *   - MERCURE – requires a recipient user identifier (always available)
 *
 * Failures on either channel are caught and logged; they never propagate
 * to the caller.
 *
 * @category EventSubscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OnboardingNotificationSubscriber implements EventSubscriberInterface
{
  // #region Constants
  private const string NOTIFICATION_TYPE = 'onboarding.organization_session_completed';

  private const string EMAIL_TEMPLATE = 'notification/email/onboarding_organization_completed.html.twig';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NotificationPort $notificationPort the notification inbound port
   * @param QueryBusPort $queryBus the shared query bus
   * @param LoggerInterface $logger the PSR logger
   */
  public function __construct(
    private NotificationPort $notificationPort,
    private QueryBusPort $queryBus,
    private LoggerInterface $logger,
  ) {
  }
  // #endregion

  // #region EventSubscriberInterface
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array
  {
    return [
      OrganizationOnboardingSessionCompletedEvent::class => 'onOrganizationOnboardingSessionCompleted',
    ];
  }
  // #endregion

  // #region Methods
  /**
   * Method onOrganizationOnboardingSessionCompleted.
   *
   * Handles the event by sending an email (when the user e‑mail is available)
   * and a real-time Mercure notification.
   *
   * @since 1.0.0
   *
   * @param OrganizationOnboardingSessionCompletedEvent $event the domain event
   */
  public function onOrganizationOnboardingSessionCompleted(
    OrganizationOnboardingSessionCompletedEvent $event,
  ): void {
    try {
      $recipientEmail = $this->resolveUserEmail($event->userId);

      $channels = null !== $recipientEmail
        ? [NotificationChannel::EMAIL, NotificationChannel::MERCURE]
        : [NotificationChannel::MERCURE];

      $completedAt = $event->completedAt->format('c');

      $this->notificationPort->send(new SendNotificationRequest(
        type: self::NOTIFICATION_TYPE,
        subject: 'Your organization is ready!',
        body: sprintf(
          'Congratulations! Your organization onboarding (session %s) has been completed on %s.',
          $event->sessionId,
          $completedAt,
        ),
        channels: $channels,
        payload: [
          'organizationId' => $event->targetOrganizationId,
          'sessionId' => $event->sessionId,
          'completedAt' => $completedAt,
        ],
        deliveryPayload: [
          NotificationChannel::EMAIL->value => [
            'template' => self::EMAIL_TEMPLATE,
            'context' => [
              'organizationId' => $event->targetOrganizationId,
              'completedAt' => $completedAt,
            ],
          ],
        ],
        recipientUserId: $event->userId,
        recipientEmail: $recipientEmail,
      ));
    } catch (Throwable $exception) {
      $this->logger->error('Failed to send onboarding completion notification.', [
        'sessionId' => $event->sessionId,
        'userId' => $event->userId,
        'organizationId' => $event->targetOrganizationId,
        'error' => $exception->getMessage(),
      ]);
    }
  }
  // #endregion

  // #region Private helpers
  /**
   * Method resolveUserEmail.
   *
   * Queries the User module via the shared bus to retrieve the authenticated
   * user's e-mail address. Returns null on any failure so notification
   * delivery can fall back to Mercure-only.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   *
   * @return ?string the user e-mail, or null when resolution fails
   */
  private function resolveUserEmail(string $userId): ?string
  {
    try {
      /** @var GetUserResult $result */
      $result = $this->queryBus->ask(new GetUserQuery(id: $userId));

      return $result->user instanceof UserView ? $result->user->email : null;
    } catch (Throwable $exception) {
      $this->logger->warning('Could not resolve user e-mail for onboarding notification.', [
        'userId' => $userId,
        'error' => $exception->getMessage(),
      ]);

      return null;
    }
  }
  // #endregion
}
