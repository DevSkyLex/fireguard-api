<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Sweep\EscalateNonConformitySlaBreaches;

use DateTimeImmutable;
use Inspection\Application\Contract\Sla\{NonConformitySlaCandidate, NonConformitySlaPage, NonConformitySlaPolicy};
use Inspection\Application\Port\Outbound\Compliance\NonConformitySlaPolicyPort;
use Inspection\Application\Port\Outbound\NonConformitySlaPort;
use Inspection\Application\Service\{NonConformitySlaNotifier, NonConformitySlaRecipientResolver};
use Inspection\Application\UseCase\Command\Sweep\EscalateNonConformitySlaBreaches\{EscalateNonConformitySlaBreachesCommand, EscalateNonConformitySlaBreachesHandler, EscalateNonConformitySlaBreachesResult};
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationNotificationPolicyPort};
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationNotificationSettings};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\ClockPort;

use function array_fill;

/**
 * Test EscalateNonConformitySlaBreachesHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EscalateNonConformitySlaBreachesHandler::class)]
final class EscalateNonConformitySlaBreachesHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e01';

  private const string INSPECTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e02';

  private const string NC_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e03';

  private const string ADMIN_MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e04';

  private const string NOW = '2026-01-10T09:00:00+00:00';

  #[Test]
  public function itEscalatesABreachedCandidateAndStampsIt(): void
  {
    // 7-day critical SLA, opened 10 days before NOW: breached.
    $candidate = $this->candidate('critical', new DateTimeImmutable('2025-12-31T09:00:00+00:00'));
    $candidates = $this->createMock(NonConformitySlaPort::class);
    $candidates->expects(self::once())->method('pageOpenUnnotified')->willReturn(new NonConformitySlaPage([$candidate]));
    $candidates->expects(self::once())
      ->method('markSlaBreachNotified')
      ->with(self::NC_ID, self::equalTo(new DateTimeImmutable(self::NOW)));

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request): bool {
        self::assertSame('non_conformity.sla_breached', $request->type);
        self::assertSame(self::NC_ID, $request->payload['nonConformityId']);
        self::assertSame(self::INSPECTION_ID, $request->payload['inspectionId']);
        self::assertSame(7, $request->payload['slaDays']);
        self::assertSame(self::ORGANIZATION_ID, $request->organizationId);

        return true;
      }))
      ->willReturn($this->sent());

    $result = $this->handler($candidates, $notifications)(new EscalateNonConformitySlaBreachesCommand());

    self::assertInstanceOf(EscalateNonConformitySlaBreachesResult::class, $result);
    self::assertSame(1, $result->escalatedCount);
  }

  #[Test]
  public function itLeavesACandidateWithinItsSlaUnnotifiedAndUnstamped(): void
  {
    // 90-day low SLA, opened 10 days before NOW: within the SLA.
    $candidate = $this->candidate('low', new DateTimeImmutable('2025-12-31T09:00:00+00:00'));
    $candidates = $this->createMock(NonConformitySlaPort::class);
    $candidates->method('pageOpenUnnotified')->willReturn(new NonConformitySlaPage([$candidate]));
    $candidates->expects(self::never())->method('markSlaBreachNotified');

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $result = $this->handler($candidates, $notifications)(new EscalateNonConformitySlaBreachesCommand());

    self::assertSame(0, $result->escalatedCount);
  }

  #[Test]
  public function itStaysSilentWhenEveryBreachIsAlreadyStamped(): void
  {
    // The anti-duplicate guard lives in the port's query (WHERE
    // sla_breach_notified_at IS NULL): a candidate the port never returns is
    // never re-escalated. This proves the handler does not bypass that.
    $candidates = $this->createMock(NonConformitySlaPort::class);
    $candidates->method('pageOpenUnnotified')->willReturn(new NonConformitySlaPage([]));
    $candidates->expects(self::never())->method('markSlaBreachNotified');

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $result = $this->handler($candidates, $notifications)(new EscalateNonConformitySlaBreachesCommand());

    self::assertSame(0, $result->escalatedCount);
  }

  #[Test]
  public function itSkipsACandidateWhoseSeverityHasNoSla(): void
  {
    $candidate = $this->candidate('bizarre', new DateTimeImmutable('2020-01-01T00:00:00+00:00'));
    $candidates = $this->createMock(NonConformitySlaPort::class);
    $candidates->method('pageOpenUnnotified')->willReturn(new NonConformitySlaPage([$candidate]));
    $candidates->expects(self::never())->method('markSlaBreachNotified');

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $result = $this->handler($candidates, $notifications)(new EscalateNonConformitySlaBreachesCommand());

    self::assertSame(0, $result->escalatedCount);
  }

  #[Test]
  public function itResolvesTheSlaPolicyOncePerOrganization(): void
  {
    $first = $this->candidate('critical', new DateTimeImmutable('2025-12-01T09:00:00+00:00'));
    $second = $this->candidate('high', new DateTimeImmutable('2025-12-01T09:00:00+00:00'), '018f0b68-6758-7a12-8a1d-3f0d97f63e05');
    $candidates = $this->createStub(NonConformitySlaPort::class);
    $candidates->method('pageOpenUnnotified')->willReturn(new NonConformitySlaPage([$first, $second]));

    $slaPolicy = $this->createMock(NonConformitySlaPolicyPort::class);
    $slaPolicy->expects(self::once())
      ->method('slaPolicy')
      ->with(self::ORGANIZATION_ID)
      ->willReturn(new NonConformitySlaPolicy(['critical' => 7, 'high' => 14]));

    $notifications = $this->createStub(NotificationPort::class);
    $notifications->method('send')->willReturn($this->sent());

    $result = $this->handler($candidates, $notifications, $slaPolicy)(new EscalateNonConformitySlaBreachesCommand());

    self::assertSame(2, $result->escalatedCount);
  }

  #[Test]
  public function itPagesThroughEveryCandidateUntilAShortPageIsReturned(): void
  {
    $fullPage = array_fill(0, 200, $this->candidate('critical', new DateTimeImmutable('2025-12-01T09:00:00+00:00')));
    $candidates = $this->createMock(NonConformitySlaPort::class);
    $candidates->expects(self::exactly(2))
      ->method('pageOpenUnnotified')
      ->willReturnOnConsecutiveCalls(
        new NonConformitySlaPage($fullPage),
        new NonConformitySlaPage([]),
      );

    $notifications = $this->createStub(NotificationPort::class);
    $notifications->method('send')->willReturn($this->sent());

    $result = $this->handler($candidates, $notifications)(new EscalateNonConformitySlaBreachesCommand());

    self::assertSame(200, $result->escalatedCount);
  }

  private function candidate(string $severity, DateTimeImmutable $createdAt, string $id = self::NC_ID): NonConformitySlaCandidate
  {
    return new NonConformitySlaCandidate(
      $id,
      self::INSPECTION_ID,
      self::ORGANIZATION_ID,
      $severity,
      $createdAt,
    );
  }

  private function handler(
    NonConformitySlaPort $candidates,
    NotificationPort $notifications,
    ?NonConformitySlaPolicyPort $slaPolicy = null,
  ): EscalateNonConformitySlaBreachesHandler {
    if (null === $slaPolicy) {
      $stub = $this->createStub(NonConformitySlaPolicyPort::class);
      $stub->method('slaPolicy')->willReturn(new NonConformitySlaPolicy([
        'low' => 90,
        'medium' => 30,
        'high' => 14,
        'critical' => 7,
      ]));
      $slaPolicy = $stub;
    }

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable(self::NOW));

    return new EscalateNonConformitySlaBreachesHandler($candidates, $slaPolicy, $this->notifier($notifications), $clock);
  }

  private function notifier(NotificationPort $notifications): NonConformitySlaNotifier
  {
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(new OrganizationNotificationSettings());

    $admin = OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::ADMIN_MEMBER_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      'admin-user',
      true,
      new DateTimeImmutable(),
    );

    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationId')->willReturn([$admin]);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturn(['organization.inspection.write']);

    return new NonConformitySlaNotifier(
      $notifications,
      $policy,
      new NonConformitySlaRecipientResolver($members, $authorization),
    );
  }

  private function sent(): SentNotification
  {
    return new SentNotification(
      '018f0b68-6758-7a12-8a1d-3f0d97f63e99',
      'non_conformity.sla_breached',
      'Non-conformity resolution SLA breached',
      'body',
      [NotificationChannel::MERCURE->value],
      [],
      [NotificationChannel::MERCURE->value => true],
      new DateTimeImmutable(),
      'admin-user',
      null,
    );
  }
}
