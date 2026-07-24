<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Sweep\MaterializeDueRecurrences;

use DateTimeImmutable;
use Intervention\Application\Contract\Draft\{CreateInterventionDraftRequest, CreatedInterventionDraft};
use Intervention\Application\Contract\Recurrence\{InterventionRecurrencePage, InterventionRecurrenceView};
use Intervention\Application\Contract\Template\InterventionTemplateView;
use Intervention\Application\Port\Inbound\InterventionDraftFactoryPort;
use Intervention\Application\Port\Outbound\{InterventionLabelPort, InterventionRecurrencePort, InterventionTemplatePort};
use Intervention\Application\Service\{InterventionMemberPolicy, InterventionRecurrenceNotifier, InterventionRecurrenceRecipientResolver, InterventionTemplateInstantiator};
use Intervention\Application\UseCase\Command\Sweep\MaterializeDueRecurrences\{MaterializeDueRecurrencesCommand, MaterializeDueRecurrencesHandler};
use Intervention\Domain\Event\Recurrence\InterventionRecurrenceMaterializedEvent;
use Notification\Application\Contract\Notification\SendNotificationRequest;
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationNotificationPolicyPort};
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationNotificationSettings};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\VoidResult;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

use function array_fill;

/**
 * Test MaterializeDueRecurrencesHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaterializeDueRecurrencesHandler::class)]
final class MaterializeDueRecurrencesHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63d01';

  private const string TEMPLATE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63d02';

  private const string RECURRENCE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63d03';

  private const string RUN_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63d04';

  private const string RESPONSIBLE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63d05';

  #[Test]
  public function itSkipsAnOccurrenceAlreadyClaimedByAnotherTick(): void
  {
    $recurrence = $this->recurrence();
    $recurrences = $this->createMock(InterventionRecurrencePort::class);
    $recurrences->method('pageDueForMaterialization')->willReturn(new InterventionRecurrencePage([$recurrence], 0, 200, 0));
    $recurrences->expects(self::once())->method('reserveRun')->willReturn(null);
    $recurrences->expects(self::never())->method('markRunSucceeded');
    $recurrences->expects(self::never())->method('markRunFailed');
    $recurrences->expects(self::never())->method('advanceNextOccurrence');

    $draftFactory = $this->createMock(InterventionDraftFactoryPort::class);
    $draftFactory->expects(self::never())->method('create');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = $this->handler($recurrences, $this->templates(), $draftFactory, $this->notifications(), $eventDispatcher);

    $result = $handler(new MaterializeDueRecurrencesCommand());

    self::assertInstanceOf(VoidResult::class, $result);
  }

  #[Test]
  public function itMaterializesSuccessfullyAndAdvancesTheOccurrence(): void
  {
    $recurrence = $this->recurrence();
    $recurrences = $this->createMock(InterventionRecurrencePort::class);
    $recurrences->method('pageDueForMaterialization')->willReturn(new InterventionRecurrencePage([$recurrence], 0, 200, 0));
    $recurrences->expects(self::once())->method('reserveRun')->willReturn(self::RUN_ID);
    $recurrences->expects(self::once())->method('markRunSucceeded')->with(self::RUN_ID, 'created-intervention-1');
    $recurrences->expects(self::never())->method('markRunFailed');
    $recurrences->expects(self::once())
      ->method('advanceNextOccurrence')
      ->with(
        self::RECURRENCE_ID,
        self::callback(static fn (DateTimeImmutable $next): bool => '2026-02-01T00:00:00+00:00' === $next->format('c')),
        self::isInstanceOf(DateTimeImmutable::class),
      );

    $captured = null;
    $draftFactory = $this->createMock(InterventionDraftFactoryPort::class);
    $draftFactory->expects(self::once())
      ->method('create')
      ->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
        $captured = $request;

        return new CreatedInterventionDraft('created-intervention-1', 7, 1);
      });

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (InterventionRecurrenceMaterializedEvent $event): bool {
        self::assertTrue($event->succeeded);
        self::assertSame('created-intervention-1', $event->interventionId);
        self::assertNull($event->error);

        return true;
      }));

    $handler = $this->handler($recurrences, $this->templates(), $draftFactory, $notifications, $eventDispatcher);

    $handler(new MaterializeDueRecurrencesCommand());

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertSame('intervention:recurrence', $captured->origin);
    self::assertNull($captured->actorUserId);
    self::assertSame('Quarterly fire safety audit', $captured->name);
    self::assertEquals(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), $captured->plannedStartAt);
  }

  #[Test]
  public function itRecordsAFailureAdvancesAndNotifiesTheResponsibleMember(): void
  {
    $recurrence = $this->recurrence();
    $recurrences = $this->createMock(InterventionRecurrencePort::class);
    $recurrences->method('pageDueForMaterialization')->willReturn(new InterventionRecurrencePage([$recurrence], 0, 200, 0));
    $recurrences->expects(self::once())->method('reserveRun')->willReturn(self::RUN_ID);
    $recurrences->expects(self::never())->method('markRunSucceeded');
    $recurrences->expects(self::once())->method('markRunFailed')->with(self::RUN_ID, self::isString());
    $recurrences->expects(self::once())
      ->method('advanceNextOccurrence')
      ->with(self::RECURRENCE_ID, self::isInstanceOf(DateTimeImmutable::class), null);

    // The template can no longer be found: the instantiator throws, which
    // the handler must record as a failed run rather than propagate.
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(null);

    $draftFactory = $this->createMock(InterventionDraftFactoryPort::class);
    $draftFactory->expects(self::never())->method('create');

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request): bool {
        self::assertSame('intervention.recurrence_failed', $request->type);
        self::assertSame('responsible-user', $request->recipientUserId);
        self::assertSame(self::ORGANIZATION_ID, $request->organizationId);

        return true;
      }));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (InterventionRecurrenceMaterializedEvent $event): bool {
        self::assertFalse($event->succeeded);
        self::assertNull($event->interventionId);
        self::assertNotNull($event->error);

        return true;
      }));

    $handler = $this->handler($recurrences, $templates, $draftFactory, $notifications, $eventDispatcher);

    $handler(new MaterializeDueRecurrencesCommand());
  }

  #[Test]
  public function itPagesThroughEveryDueRecurrenceUntilAShortPageIsReturned(): void
  {
    // The handler's internal page size is 200: a page must be exactly that
    // size to be treated as "full" (i.e. there may be more to fetch).
    $fullPage = array_fill(0, 200, $this->recurrence());
    $recurrences = $this->createMock(InterventionRecurrencePort::class);
    $recurrences->expects(self::exactly(2))
      ->method('pageDueForMaterialization')
      ->willReturnOnConsecutiveCalls(
        new InterventionRecurrencePage($fullPage, 0, 200, 0),
        new InterventionRecurrencePage([], 0, 200, 0),
      );
    $recurrences->method('reserveRun')->willReturn(null);

    $draftFactory = $this->createMock(InterventionDraftFactoryPort::class);
    $draftFactory->expects(self::never())->method('create');

    $handler = $this->handler($recurrences, $this->templates(), $draftFactory, $this->notifications(), $this->createStub(EventDispatcherPort::class));

    $handler(new MaterializeDueRecurrencesCommand());
  }

  private function recurrence(): InterventionRecurrenceView
  {
    return new InterventionRecurrenceView(
      self::RECURRENCE_ID,
      self::ORGANIZATION_ID,
      self::TEMPLATE_ID,
      'Quarterly fire safety audit',
      null,
      self::RESPONSIBLE_ID,
      'monthly',
      1,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      'UTC',
      7,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      null,
      true,
      null,
      new DateTimeImmutable('2025-12-01T00:00:00+00:00'),
      new DateTimeImmutable('2025-12-01T00:00:00+00:00'),
    );
  }

  private function templates(): InterventionTemplatePort
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(new InterventionTemplateView(
      self::TEMPLATE_ID,
      self::ORGANIZATION_ID,
      'Fire safety audit',
      null,
      'inspection_campaign',
      'normal',
      null,
      null,
      null,
      [],
      [],
      new DateTimeImmutable(),
      new DateTimeImmutable(),
    ));

    return $templates;
  }

  private function handler(
    InterventionRecurrencePort $recurrences,
    InterventionTemplatePort $templates,
    InterventionDraftFactoryPort $draftFactory,
    NotificationPort $notifications,
    EventDispatcherPort $eventDispatcher,
  ): MaterializeDueRecurrencesHandler {
    $instantiator = new InterventionTemplateInstantiator(
      $templates,
      $draftFactory,
      $this->createStub(InterventionLabelPort::class),
      new InterventionMemberPolicy($this->createStub(OrganizationMemberRepositoryPort::class)),
    );

    $notifier = new InterventionRecurrenceNotifier(
      $notifications,
      $this->notificationPolicy(),
      $this->members(),
      new InterventionRecurrenceRecipientResolver($this->members(), $this->createStub(OrganizationAuthorizationPort::class)),
    );

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-05T00:00:00+00:00'));

    return new MaterializeDueRecurrencesHandler($recurrences, $instantiator, $notifier, $eventDispatcher, $clock);
  }

  private function notifications(): NotificationPort
  {
    return $this->createStub(NotificationPort::class);
  }

  private function notificationPolicy(): OrganizationNotificationPolicyPort
  {
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(new OrganizationNotificationSettings());

    return $policy;
  }

  private function members(): OrganizationMemberRepositoryPort
  {
    $responsible = OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::RESPONSIBLE_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      'responsible-user',
      true,
      new DateTimeImmutable(),
    );

    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findById')->willReturnCallback(
      static fn (OrganizationMemberId $id): ?OrganizationMember => self::RESPONSIBLE_ID === $id->value ? $responsible : null,
    );
    $repository->method('findByOrganizationId')->willReturn([$responsible]);

    return $repository;
  }
}
