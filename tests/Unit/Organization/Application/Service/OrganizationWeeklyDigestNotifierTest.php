<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Contract\Inspection\OpenNonConformitySummary;
use Organization\Application\Contract\Intervention\RecentInterventionSummary;
use Organization\Application\Contract\Maintenance\MaintenanceDueSummary;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Application\Service\{OrganizationWeeklyDigest, OrganizationWeeklyDigestNotifier, OrganizationWeeklyDigestRecipientResolver};
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\LoggerPort;
use Symfony\Contracts\Translation\TranslatorInterface;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function array_keys;
use function array_map;
use function sprintf;

/**
 * Test OrganizationWeeklyDigestNotifierTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationWeeklyDigestNotifier::class)]
final class OrganizationWeeklyDigestNotifierTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string FRONTEND_URL = 'https://app.fireguard.test/';

  #[Test]
  public function testNotifySendsOneLocalizedEmailPerAdministrator(): void
  {
    /** @var list<SendNotificationRequest> $requests */
    $requests = [];

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))
      ->method('send')
      ->willReturnCallback(function (SendNotificationRequest $request) use (&$requests): SentNotification {
        $requests[] = $request;

        return $this->sent();
      });

    $notifier = $this->notifier(
      $notifications,
      ['user-fr', 'user-de'],
      ['user-fr' => 'fr', 'user-de' => 'de'],
    );

    $sent = $notifier->notify(self::ORG_ID, 'ACME', $this->digest());

    self::assertSame(2, $sent);
    self::assertCount(2, $requests);

    foreach ($requests as $request) {
      self::assertSame('organization.weekly_digest', $request->type);
      self::assertSame([NotificationChannel::EMAIL], $request->channels);
      self::assertSame(self::ORG_ID, $request->organizationId);
      self::assertSame(2, $request->payload['overdueInterventions']);
      self::assertSame(1, $request->payload['maintenanceOverdue']);
      self::assertSame(3, $request->payload['openNonConformities']);

      $email = self::emailPayload($request);
      self::assertSame('notification/email/organization_weekly_digest.html.twig', $email['template']);
      self::assertSame('https://app.fireguard.test/organizations/' . self::ORG_ID, $email['context']['dashboardUrl']);
    }

    self::assertSame('user-fr', $requests[0]->recipientUserId);
    self::assertSame('user-fr@example.com', $requests[0]->recipientEmail);
    self::assertSame('fr', self::emailPayload($requests[0])['context']['locale']);

    self::assertSame(
      'en',
      self::emailPayload($requests[1])['context']['locale'],
      'An unsupported recipient locale must clamp to English.',
    );
  }

  #[Test]
  public function testNotifySkipsARecipientWhoseUserCannotBeResolved(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'user-ok' === $request->recipientUserId))
      ->willReturn($this->sent());

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      fn (GetUserQuery $query): GetUserResult => 'user-missing' === $query->id
        ? new GetUserResult(null)
        : new GetUserResult($this->userView($query->id, 'en')),
    );

    $notifier = new OrganizationWeeklyDigestNotifier(
      $notifications,
      $this->recipients(['user-missing', 'user-ok']),
      $queryBus,
      $this->translator(),
      $this->createStub(LoggerPort::class),
      self::FRONTEND_URL,
    );

    self::assertSame(1, $notifier->notify(self::ORG_ID, 'ACME', $this->digest()));
  }

  #[Test]
  public function testNotifyNeverThrowsWhenOneDeliveryFails(): void
  {
    $calls = 0;
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))
      ->method('send')
      ->willReturnCallback(function () use (&$calls): SentNotification {
        if (1 === ++$calls) {
          throw new RuntimeException('mailer down');
        }

        return $this->sent();
      });

    $notifier = $this->notifier($notifications, ['user-1', 'user-2'], []);

    self::assertSame(
      1,
      $notifier->notify(self::ORG_ID, 'ACME', $this->digest()),
      'The failed recipient must not stop delivery to the remaining ones.',
    );
  }

  /**
   * Extracts the typed email delivery payload of a captured request.
   *
   * @return array{template: string, context: array<string, mixed>}
   */
  private static function emailPayload(SendNotificationRequest $request): array
  {
    $email = $request->deliveryPayload[NotificationChannel::EMAIL->value];
    self::assertIsArray($email);

    /** @var array{template: string, context: array<string, mixed>} $email */
    return $email;
  }

  /**
   * Builds the notifier under test with a deterministic recipient set and a
   * per-user locale map (missing entries default to `en`).
   *
   * @param list<string> $userIds
   * @param array<string, string> $locales
   */
  private function notifier(NotificationPort $notifications, array $userIds, array $locales): OrganizationWeeklyDigestNotifier
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      fn (GetUserQuery $query): GetUserResult => new GetUserResult(
        $this->userView($query->id, $locales[$query->id] ?? 'en'),
      ),
    );

    return new OrganizationWeeklyDigestNotifier(
      $notifications,
      $this->recipients($userIds),
      $queryBus,
      $this->translator(),
      $this->createStub(LoggerPort::class),
      self::FRONTEND_URL,
    );
  }

  /**
   * Builds a real resolver (the concrete class is final and cannot be
   * doubled) backed by stubbed ports, so it deterministically resolves to
   * the given user ids as active organization administrators.
   *
   * @param list<string> $userIds
   */
  private function recipients(array $userIds): OrganizationWeeklyDigestRecipientResolver
  {
    $members = array_map(
      static fn (int $index, string $userId): OrganizationMember => OrganizationMember::reconstitute(
        OrganizationMemberId::fromString('550e8400-e29b-41d4-a716-4466554400' . (20 + $index)),
        OrganizationId::fromString(self::ORG_ID),
        $userId,
        true,
        new DateTimeImmutable(),
      ),
      array_keys($userIds),
      $userIds,
    );

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationId')->willReturn($members);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturn(['organization.settings.write']);

    return new OrganizationWeeklyDigestRecipientResolver($memberRepository, $authorization);
  }

  private function translator(): TranslatorInterface
  {
    $translator = $this->createStub(TranslatorInterface::class);
    $translator->method('trans')->willReturnCallback(
      static fn (string $id): string => $id,
    );

    return $translator;
  }

  private function userView(string $userId, string $locale): UserView
  {
    return new UserView(
      id: $userId,
      username: $userId,
      email: sprintf('%s@example.com', $userId),
      firstName: 'Test',
      lastName: 'User',
      avatarUrl: null,
      status: 'active',
      emailVerified: true,
      tenantId: null,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      lastLoginAt: null,
      canLogin: true,
      locale: $locale,
    );
  }

  private function digest(): OrganizationWeeklyDigest
  {
    return new OrganizationWeeklyDigest(
      overdueInterventionsCount: 2,
      overdueInterventions: [
        new RecentInterventionSummary(
          id: '550e8400-e29b-41d4-a716-446655440031',
          number: 12,
          name: 'Replace extinguisher',
          status: 'in_progress',
          priority: 'high',
          siteId: null,
          responsibleMemberId: null,
          dueAt: new DateTimeImmutable('2026-08-20T08:00:00+00:00'),
          updatedAt: new DateTimeImmutable('2026-08-21T08:00:00+00:00'),
        ),
      ],
      maintenanceDueSoonCount: 1,
      maintenanceOverdueCount: 1,
      maintenanceDeadlines: [
        new MaintenanceDueSummary(
          equipmentId: '550e8400-e29b-41d4-a716-446655440032',
          facilityId: null,
          equipmentType: 'extinguisher',
          nextDueAt: new DateTimeImmutable('2026-08-25T00:00:00+00:00'),
          overdue: true,
        ),
      ],
      openNonConformitiesCount: 3,
      slaBreachedNonConformitiesCount: 1,
      openNonConformities: [
        new OpenNonConformitySummary(
          id: '550e8400-e29b-41d4-a716-446655440033',
          inspectionId: '550e8400-e29b-41d4-a716-446655440034',
          description: 'Blocked emergency exit',
          severity: 'critical',
          status: 'open',
          dueAt: null,
          createdAt: new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
        ),
      ],
    );
  }

  private function sent(): SentNotification
  {
    return new SentNotification(
      '550e8400-e29b-41d4-a716-446655440099',
      'organization.weekly_digest',
      'digest.emailSubject',
      'body',
      [NotificationChannel::EMAIL->value],
      [],
      [NotificationChannel::EMAIL->value => true],
      new DateTimeImmutable(),
      'user-1',
      null,
    );
  }
}
