<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Notification\Application\Contract\Notification\{NotificationChannel, NotificationType, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Contract\Inspection\OpenNonConformitySummary;
use Organization\Application\Contract\Intervention\RecentInterventionSummary;
use Organization\Application\Contract\Maintenance\MaintenanceDueSummary;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\LoggerPort;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function array_map;
use function htmlspecialchars;
use function in_array;
use function rtrim;
use function sprintf;

/**
 * Service OrganizationWeeklyDigestNotifier.
 *
 * Sends the weekly organization digest email to the organization's
 * administrators. Email-only by design: the digest is a periodic summary,
 * not a real-time event, so no Mercure/in-app delivery — the organization's
 * `weeklyDigest` category toggle and `emailEnabled` channel toggle are
 * checked upstream by the sweep handler, and the recipient's own per-channel
 * preference for the `organization` category is enforced by the Notification
 * module. Best-effort per recipient: one failed delivery must not skip the
 * rest, and no failure ever fails the recurring sweep.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationWeeklyDigestNotifier
{
  /**
   * Email locales the digest template supports; any other recipient locale
   * falls back to English — mirrors `OrganizationInvitationNotifier`.
   */
  private const array SUPPORTED_LOCALES = ['en', 'fr', 'es'];

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NotificationPort $notifications the notification inbound port
   * @param OrganizationWeeklyDigestRecipientResolver $recipients the digest recipient resolver
   * @param QueryBusPort $queryBus the shared query bus, for recipient email/locale resolution
   * @param TranslatorInterface $translator the translator, for the localized subject and body
   * @param LoggerPort $logger the logger port
   * @param string $frontendUrl the public frontend base URL for the dashboard deep link
   */
  public function __construct(
    private NotificationPort $notifications,
    private OrganizationWeeklyDigestRecipientResolver $recipients,
    private QueryBusPort $queryBus,
    private TranslatorInterface $translator,
    private LoggerPort $logger,
    private string $frontendUrl,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method notify.
   *
   * Sends the digest email to every administrator of the organization,
   * localized per recipient.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $organizationName the organization display name
   * @param OrganizationWeeklyDigest $digest the digest snapshot to send
   *
   * @return int the number of digest emails sent
   */
  public function notify(string $organizationId, string $organizationName, OrganizationWeeklyDigest $digest): int
  {
    $sent = 0;

    foreach ($this->recipients->organizationAdministrators($organizationId) as $userId) {
      try {
        $user = $this->resolveUser($userId);
        if (!$user instanceof UserView) {
          continue;
        }

        $locale = in_array($user->locale, self::SUPPORTED_LOCALES, true) ? $user->locale : 'en';
        $this->send($organizationId, $organizationName, $digest, $user, $locale);
        ++$sent;
      } catch (Throwable $exception) {
        $this->logger->warning('Weekly digest delivery failed for one recipient.', [
          'organizationId' => $organizationId,
          'userId' => $userId,
          'error' => $exception->getMessage(),
        ]);
      }
    }

    return $sent;
  }

  /**
   * Method send.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $organizationName the organization display name
   * @param OrganizationWeeklyDigest $digest the digest snapshot
   * @param UserView $user the recipient
   * @param string $locale the recipient email locale (en/fr/es)
   */
  private function send(
    string $organizationId,
    string $organizationName,
    OrganizationWeeklyDigest $digest,
    UserView $user,
    string $locale,
  ): void {
    $dashboardUrl = sprintf('%s/organizations/%s', rtrim($this->frontendUrl, '/'), $organizationId);
    $dateFormat = match ($locale) {
      'fr', 'es' => 'd/m/Y',
      default => 'M j, Y',
    };

    $subject = $this->translator->trans('digest.emailSubject', ['%org%' => $organizationName], 'emails', $locale);

    $this->notifications->send(new SendNotificationRequest(
      type: NotificationType::ORGANIZATION_WEEKLY_DIGEST,
      subject: $subject,
      body: sprintf(
        '<p>%s</p>',
        $this->translator->trans(
          'digest.intro',
          ['%org%' => htmlspecialchars($organizationName)],
          'emails',
          $locale,
        ),
      ),
      channels: [NotificationChannel::EMAIL],
      payload: [
        'organizationId' => $organizationId,
        'overdueInterventions' => $digest->overdueInterventionsCount,
        'maintenanceDueSoon' => $digest->maintenanceDueSoonCount,
        'maintenanceOverdue' => $digest->maintenanceOverdueCount,
        'openNonConformities' => $digest->openNonConformitiesCount,
        'slaBreachedNonConformities' => $digest->slaBreachedNonConformitiesCount,
      ],
      deliveryPayload: [
        NotificationChannel::EMAIL->value => [
          'template' => 'notification/email/organization_weekly_digest.html.twig',
          'context' => [
            'locale' => $locale,
            'organizationName' => $organizationName,
            'dashboardUrl' => $dashboardUrl,
            'overdueInterventionsCount' => $digest->overdueInterventionsCount,
            'overdueInterventions' => array_map(
              static fn (RecentInterventionSummary $summary): array => [
                'number' => $summary->number,
                'name' => $summary->name,
                'dueAt' => $summary->dueAt?->format($dateFormat),
              ],
              $digest->overdueInterventions,
            ),
            'maintenanceDueSoonCount' => $digest->maintenanceDueSoonCount,
            'maintenanceOverdueCount' => $digest->maintenanceOverdueCount,
            'maintenanceDeadlines' => array_map(
              static fn (MaintenanceDueSummary $summary): array => [
                'equipmentType' => $summary->equipmentType,
                'nextDueAt' => $summary->nextDueAt->format($dateFormat),
                'overdue' => $summary->overdue,
              ],
              $digest->maintenanceDeadlines,
            ),
            'openNonConformitiesCount' => $digest->openNonConformitiesCount,
            'slaBreachedNonConformitiesCount' => $digest->slaBreachedNonConformitiesCount,
            'openNonConformities' => array_map(
              static fn (OpenNonConformitySummary $summary): array => [
                'description' => $summary->description,
                'severity' => $summary->severity,
                'openedAt' => $summary->createdAt->format($dateFormat),
              ],
              $digest->openNonConformities,
            ),
          ],
        ],
      ],
      recipientUserId: $user->id,
      recipientEmail: $user->email,
      organizationId: $organizationId,
    ));
  }

  /**
   * Method resolveUser.
   *
   * Resolves the recipient's email and locale through the User module's
   * query bus — mirrors `OnboardingNotificationSubscriber`.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   *
   * @return ?UserView the user view, or null when resolution fails
   */
  private function resolveUser(string $userId): ?UserView
  {
    /** @var GetUserResult $result */
    $result = $this->queryBus->ask(new GetUserQuery(id: $userId));

    return $result->user instanceof UserView ? $result->user : null;
  }
  // #endregion
}
