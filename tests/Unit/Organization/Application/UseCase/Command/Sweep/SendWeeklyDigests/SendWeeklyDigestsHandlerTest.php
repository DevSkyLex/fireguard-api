<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Sweep\SendWeeklyDigests;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Contract\Intervention\RecentInterventionSummary;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationNotificationPolicyPort};
use Organization\Application\Port\Outbound\{InterventionStatisticsPort, MaintenanceStatisticsPort, NonConformityStatisticsPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Service\{OrganizationWeeklyDigestNotifier, OrganizationWeeklyDigestRecipientResolver};
use Organization\Application\UseCase\Command\Sweep\SendWeeklyDigests\{SendWeeklyDigestsCommand, SendWeeklyDigestsHandler};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName, OrganizationNotificationSettings};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\{ClockPort, LoggerPort};
use Symfony\Contracts\Translation\TranslatorInterface;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function array_keys;
use function array_map;
use function sprintf;

/**
 * Test SendWeeklyDigestsHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SendWeeklyDigestsHandler::class)]
final class SendWeeklyDigestsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string OTHER_ORG_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string NOW = '2026-08-31T06:00:00+00:00';

  // #region Tests
  #[Test]
  public function testSweepAggregatesAndSendsTheDigestToEveryAdministrator(): void
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

    $interventions = $this->createMock(InterventionStatisticsPort::class);
    $interventions->expects(self::once())
      ->method('countOverview')
      ->with(self::ORG_ID)
      ->willReturn(['total' => 10, 'open' => 6, 'overdue' => 2]);
    $interventions->expects(self::once())
      ->method('findOverdueInterventions')
      ->with(self::ORG_ID, self::anything(), 5)
      ->willReturn([$this->interventionSummary()]);

    $maintenance = $this->createMock(MaintenanceStatisticsPort::class);
    $maintenance->expects(self::once())
      ->method('countDueOverview')
      ->with(self::ORG_ID)
      ->willReturn(['due_soon' => 3, 'overdue' => 1]);
    $maintenance->expects(self::once())
      ->method('findDueSchedules')
      ->with(self::ORG_ID, self::anything(), self::anything(), 5)
      ->willReturn([]);

    $nonConformities = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformities->expects(self::once())
      ->method('countNonConformitiesByStatus')
      ->with(self::ORG_ID)
      ->willReturn(['open' => 2, 'in_progress' => 1, 'done' => 9]);
    $nonConformities->expects(self::once())
      ->method('countSlaBreachedNonConformities')
      ->with(self::ORG_ID)
      ->willReturn(1);
    $nonConformities->expects(self::once())
      ->method('findOpenNonConformities')
      ->with(self::ORG_ID, 5)
      ->willReturn([]);

    $handler = $this->handler(
      organizationIds: [self::ORG_ID],
      notifications: $notifications,
      interventions: $interventions,
      maintenance: $maintenance,
      nonConformities: $nonConformities,
      adminUserIds: ['user-1', 'user-2'],
    );

    $result = $handler(new SendWeeklyDigestsCommand());

    self::assertSame(1, $result->organizationsScanned);
    self::assertSame(2, $result->digestsSent);

    self::assertSame(['user-1', 'user-2'], array_map(
      static fn (SendNotificationRequest $request): string => (string) $request->recipientUserId,
      $requests,
    ));

    foreach ($requests as $request) {
      self::assertSame('organization.weekly_digest', $request->type);
      self::assertSame([NotificationChannel::EMAIL], $request->channels);
      self::assertSame(2, $request->payload['overdueInterventions']);
      self::assertSame(3, $request->payload['maintenanceDueSoon']);
      self::assertSame(1, $request->payload['maintenanceOverdue']);
      self::assertSame(3, $request->payload['openNonConformities']);
      self::assertSame(1, $request->payload['slaBreachedNonConformities']);
    }
  }

  #[Test]
  public function testSweepStaysSilentWhenEveryCounterIsZero(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $interventions = $this->createMock(InterventionStatisticsPort::class);
    $interventions->method('countOverview')->willReturn(['total' => 4, 'open' => 4, 'overdue' => 0]);
    $interventions->expects(self::never())->method('findOverdueInterventions');

    $maintenance = $this->createMock(MaintenanceStatisticsPort::class);
    $maintenance->method('countDueOverview')->willReturn(['due_soon' => 0, 'overdue' => 0]);
    $maintenance->expects(self::never())->method('findDueSchedules');

    $nonConformities = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformities->method('countNonConformitiesByStatus')->willReturn(['done' => 12]);
    $nonConformities->method('countSlaBreachedNonConformities')->willReturn(0);
    $nonConformities->expects(self::never())->method('findOpenNonConformities');

    $handler = $this->handler(
      organizationIds: [self::ORG_ID],
      notifications: $notifications,
      interventions: $interventions,
      maintenance: $maintenance,
      nonConformities: $nonConformities,
    );

    $result = $handler(new SendWeeklyDigestsCommand());

    self::assertSame(1, $result->organizationsScanned);
    self::assertSame(0, $result->digestsSent);
  }

  #[Test]
  public function testSweepSkipsAnOrganizationWhoseWeeklyDigestToggleIsOff(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $interventions = $this->createMock(InterventionStatisticsPort::class);
    $interventions->expects(self::never())->method('countOverview');

    $handler = $this->handler(
      organizationIds: [self::ORG_ID],
      notifications: $notifications,
      interventions: $interventions,
      policy: new OrganizationNotificationSettings(weeklyDigest: false),
    );

    $result = $handler(new SendWeeklyDigestsCommand());

    self::assertSame(0, $result->digestsSent);
  }

  #[Test]
  public function testSweepSkipsAnOrganizationWhoseEmailChannelIsDisabled(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $interventions = $this->createMock(InterventionStatisticsPort::class);
    $interventions->expects(self::never())->method('countOverview');

    $handler = $this->handler(
      organizationIds: [self::ORG_ID],
      notifications: $notifications,
      interventions: $interventions,
      policy: new OrganizationNotificationSettings(emailEnabled: false),
    );

    $result = $handler(new SendWeeklyDigestsCommand());

    self::assertSame(0, $result->digestsSent);
  }

  #[Test]
  public function testOneFailingOrganizationDoesNotStarveTheRestOfTheSweep(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => self::OTHER_ORG_ID === $request->organizationId))
      ->willReturn($this->sent());

    $interventions = $this->createStub(InterventionStatisticsPort::class);
    $interventions->method('countOverview')->willReturnCallback(
      static function (string $organizationId): array {
        if (self::ORG_ID === $organizationId) {
          throw new RuntimeException('statistics store down');
        }

        return ['total' => 1, 'open' => 1, 'overdue' => 1];
      },
    );
    $interventions->method('findOverdueInterventions')->willReturn([]);

    $handler = $this->handler(
      organizationIds: [self::ORG_ID, self::OTHER_ORG_ID],
      notifications: $notifications,
      interventions: $interventions,
    );

    $result = $handler(new SendWeeklyDigestsCommand());

    self::assertSame(2, $result->organizationsScanned);
    self::assertSame(1, $result->digestsSent);
  }
  // #endregion

  // #region Helpers
  /**
   * Builds the handler under test around a real notifier and resolver
   * (both final, so they cannot be doubled), with every outbound port
   * stubbed or mocked.
   *
   * @param list<string> $organizationIds
   * @param list<string> $adminUserIds
   */
  private function handler(
    array $organizationIds,
    NotificationPort $notifications,
    ?InterventionStatisticsPort $interventions = null,
    ?MaintenanceStatisticsPort $maintenance = null,
    ?NonConformityStatisticsPort $nonConformities = null,
    ?OrganizationNotificationSettings $policy = null,
    array $adminUserIds = ['user-1'],
  ): SendWeeklyDigestsHandler {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('pageActiveIds')->willReturnCallback(
      static fn (int $limit, int $offset): array => 0 === $offset ? $organizationIds : [],
    );
    $organizations->method('findById')->willReturnCallback(
      static fn (OrganizationId $id): Organization => Organization::create(
        $id,
        new OrganizationName('ACME'),
        'user-owner',
      ),
    );

    $policyPort = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policyPort->method('notificationPolicy')->willReturn($policy ?? new OrganizationNotificationSettings());

    if (null === $interventions) {
      $interventions = $this->createStub(InterventionStatisticsPort::class);
      $interventions->method('countOverview')->willReturn(['total' => 0, 'open' => 0, 'overdue' => 0]);
      $interventions->method('findOverdueInterventions')->willReturn([]);
    }

    if (null === $maintenance) {
      $maintenance = $this->createStub(MaintenanceStatisticsPort::class);
      $maintenance->method('countDueOverview')->willReturn(['due_soon' => 0, 'overdue' => 0]);
      $maintenance->method('findDueSchedules')->willReturn([]);
    }

    if (null === $nonConformities) {
      $nonConformities = $this->createStub(NonConformityStatisticsPort::class);
      $nonConformities->method('countNonConformitiesByStatus')->willReturn([]);
      $nonConformities->method('countSlaBreachedNonConformities')->willReturn(0);
      $nonConformities->method('findOpenNonConformities')->willReturn([]);
    }

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable(self::NOW));

    return new SendWeeklyDigestsHandler(
      organizations: $organizations,
      policy: $policyPort,
      interventionStatistics: $interventions,
      maintenanceStatistics: $maintenance,
      nonConformityStatistics: $nonConformities,
      notifier: $this->notifier($notifications, $adminUserIds),
      clock: $clock,
      logger: $this->createStub(LoggerPort::class),
    );
  }

  /**
   * Builds a real notifier over a mocked notification port, resolving the
   * given user ids as administrators of every organization.
   *
   * @param list<string> $adminUserIds
   */
  private function notifier(NotificationPort $notifications, array $adminUserIds): OrganizationWeeklyDigestNotifier
  {
    $members = array_map(
      static fn (int $index, string $userId): OrganizationMember => OrganizationMember::reconstitute(
        OrganizationMemberId::fromString('550e8400-e29b-41d4-a716-4466554400' . (20 + $index)),
        OrganizationId::fromString(self::ORG_ID),
        $userId,
        true,
        new DateTimeImmutable(),
      ),
      array_keys($adminUserIds),
      $adminUserIds,
    );

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationId')->willReturn($members);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturn(['organization.settings.write']);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      static fn (GetUserQuery $query): GetUserResult => new GetUserResult(new UserView(
        id: $query->id,
        username: $query->id,
        email: sprintf('%s@example.com', $query->id),
        firstName: 'Test',
        lastName: 'User',
        avatarUrl: null,
        status: 'active',
        emailVerified: true,
        tenantId: null,
        createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        lastLoginAt: null,
        canLogin: true,
        locale: 'en',
      )),
    );

    $translator = $this->createStub(TranslatorInterface::class);
    $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

    return new OrganizationWeeklyDigestNotifier(
      $notifications,
      new OrganizationWeeklyDigestRecipientResolver($memberRepository, $authorization),
      $queryBus,
      $translator,
      $this->createStub(LoggerPort::class),
      'https://app.fireguard.test',
    );
  }

  private function interventionSummary(): RecentInterventionSummary
  {
    return new RecentInterventionSummary(
      id: '550e8400-e29b-41d4-a716-446655440031',
      number: 12,
      name: 'Replace extinguisher',
      status: 'in_progress',
      priority: 'high',
      siteId: null,
      responsibleMemberId: null,
      dueAt: new DateTimeImmutable('2026-08-20T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-08-21T08:00:00+00:00'),
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
