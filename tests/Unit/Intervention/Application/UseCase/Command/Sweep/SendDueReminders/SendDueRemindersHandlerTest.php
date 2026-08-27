<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Sweep\SendDueReminders;

use DateTimeImmutable;
use Intervention\Application\Contract\Reminder\{InterventionReminderCandidate, InterventionReminderPage};
use Intervention\Application\Port\Outbound\InterventionReminderPort;
use Intervention\Application\Service\{InterventionNotificationService, InterventionRecurrenceRecipientResolver, InterventionReviewerRecipientResolver};
use Intervention\Application\UseCase\Command\Sweep\SendDueReminders\{SendDueRemindersCommand, SendDueRemindersHandler, SendDueRemindersResult};
use Notification\Application\Contract\Notification\SendNotificationRequest;
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
 * Test SendDueRemindersHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SendDueRemindersHandler::class)]
final class SendDueRemindersHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e01';

  private const string INTERVENTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e02';

  private const string RESPONSIBLE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e03';

  private const string PARTICIPANT_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e04';

  private const string NOW = '2026-01-10T09:00:00+00:00';

  #[Test]
  public function itSendsADueSoonReminderAndStampsIt(): void
  {
    $candidate = $this->candidate(new DateTimeImmutable('2026-01-11T09:00:00+00:00'));
    $reminders = $this->createMock(InterventionReminderPort::class);
    $reminders->expects(self::once())->method('pageDueSoon')->willReturn(new InterventionReminderPage([$candidate]));
    $reminders->method('pageOverdue')->willReturn(new InterventionReminderPage([]));
    $reminders->expects(self::once())
      ->method('markDueSoonNotified')
      ->with(self::INTERVENTION_ID, self::equalTo(new DateTimeImmutable(self::NOW)));
    $reminders->expects(self::never())->method('markOverdueNotified');

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request): bool {
        self::assertSame('intervention.due_soon', $request->type);
        self::assertSame(self::INTERVENTION_ID, $request->payload['interventionId']);
        self::assertSame(self::ORGANIZATION_ID, $request->organizationId);

        return true;
      }));

    $result = $this->handler($reminders, $notifications)(new SendDueRemindersCommand());

    self::assertInstanceOf(SendDueRemindersResult::class, $result);
    self::assertSame(1, $result->dueSoonCount);
    self::assertSame(0, $result->overdueCount);
  }

  #[Test]
  public function itSendsAnOverdueReminderAndStampsIt(): void
  {
    $candidate = $this->candidate(new DateTimeImmutable('2026-01-09T09:00:00+00:00'));
    $reminders = $this->createMock(InterventionReminderPort::class);
    $reminders->method('pageDueSoon')->willReturn(new InterventionReminderPage([]));
    $reminders->expects(self::once())->method('pageOverdue')->willReturn(new InterventionReminderPage([$candidate]));
    $reminders->expects(self::once())
      ->method('markOverdueNotified')
      ->with(self::INTERVENTION_ID, self::equalTo(new DateTimeImmutable(self::NOW)));
    $reminders->expects(self::never())->method('markDueSoonNotified');

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request): bool {
        self::assertSame('intervention.overdue', $request->type);

        return true;
      }));

    $result = $this->handler($reminders, $notifications)(new SendDueRemindersCommand());

    self::assertSame(0, $result->dueSoonCount);
    self::assertSame(1, $result->overdueCount);
  }

  #[Test]
  public function itSkipsACandidateAlreadyStampedForTheCurrentThreshold(): void
  {
    // The anti-spam guard lives in the port's query (WHERE ..._notified_at IS
    // NULL): a candidate the port never returns is never re-notified. This
    // proves the handler does not independently re-check or bypass that.
    $reminders = $this->createMock(InterventionReminderPort::class);
    $reminders->method('pageDueSoon')->willReturn(new InterventionReminderPage([]));
    $reminders->method('pageOverdue')->willReturn(new InterventionReminderPage([]));
    $reminders->expects(self::never())->method('markDueSoonNotified');
    $reminders->expects(self::never())->method('markOverdueNotified');

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $result = $this->handler($reminders, $notifications)(new SendDueRemindersCommand());

    self::assertSame(0, $result->dueSoonCount);
    self::assertSame(0, $result->overdueCount);
  }

  #[Test]
  public function itDeduplicatesTheResponsibleWhenAlsoAParticipant(): void
  {
    $candidate = new InterventionReminderCandidate(
      self::INTERVENTION_ID,
      self::ORGANIZATION_ID,
      12,
      'Annual inventory',
      new DateTimeImmutable('2026-01-11T09:00:00+00:00'),
      self::RESPONSIBLE_ID,
      [self::RESPONSIBLE_ID, self::PARTICIPANT_ID],
    );
    $reminders = $this->createStub(InterventionReminderPort::class);
    $reminders->method('pageDueSoon')->willReturn(new InterventionReminderPage([$candidate]));
    $reminders->method('pageOverdue')->willReturn(new InterventionReminderPage([]));

    $recipients = [];
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request) use (&$recipients): bool {
        $recipients[] = $request->recipientUserId;

        return true;
      }));

    $this->handler($reminders, $notifications)(new SendDueRemindersCommand());

    // One recipient per unique member — the responsible id is never notified
    // twice for appearing in both roles.
    self::assertCount(2, $recipients);
  }

  #[Test]
  public function itPagesThroughEveryDueSoonCandidateUntilAShortPageIsReturned(): void
  {
    $fullPage = array_fill(0, 200, $this->candidate(new DateTimeImmutable('2026-01-11T09:00:00+00:00')));
    $reminders = $this->createMock(InterventionReminderPort::class);
    $reminders->expects(self::exactly(2))
      ->method('pageDueSoon')
      ->willReturnOnConsecutiveCalls(
        new InterventionReminderPage($fullPage),
        new InterventionReminderPage([]),
      );
    $reminders->method('pageOverdue')->willReturn(new InterventionReminderPage([]));

    $result = $this->handler($reminders, $this->createStub(NotificationPort::class))(new SendDueRemindersCommand());

    self::assertSame(200, $result->dueSoonCount);
  }

  private function candidate(DateTimeImmutable $dueAt): InterventionReminderCandidate
  {
    return new InterventionReminderCandidate(
      self::INTERVENTION_ID,
      self::ORGANIZATION_ID,
      12,
      'Annual inventory',
      $dueAt,
      self::RESPONSIBLE_ID,
      [],
    );
  }

  private function handler(InterventionReminderPort $reminders, NotificationPort $notifications): SendDueRemindersHandler
  {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable(self::NOW));

    return new SendDueRemindersHandler($reminders, $this->notificationService($notifications), $clock);
  }

  private function notificationService(NotificationPort $notifications): InterventionNotificationService
  {
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(new OrganizationNotificationSettings());

    $responsible = OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::RESPONSIBLE_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      'responsible-user',
      true,
      new DateTimeImmutable(),
    );
    $participant = OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::PARTICIPANT_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      'participant-user',
      true,
      new DateTimeImmutable(),
    );

    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturnCallback(
      static fn (OrganizationMemberId $id): ?OrganizationMember => match ($id->value) {
        self::RESPONSIBLE_ID => $responsible,
        self::PARTICIPANT_ID => $participant,
        default => null,
      },
    );

    $reviewers = new InterventionReviewerRecipientResolver($members, $this->createStub(OrganizationAuthorizationPort::class));

    $adminMembers = $this->createStub(OrganizationMemberRepositoryPort::class);
    $adminMembers->method('findByOrganizationId')->willReturn([]);
    $admins = new InterventionRecurrenceRecipientResolver($adminMembers, $this->createStub(OrganizationAuthorizationPort::class));

    return new InterventionNotificationService($notifications, $members, $policy, $reviewers, $admins);
  }
}
