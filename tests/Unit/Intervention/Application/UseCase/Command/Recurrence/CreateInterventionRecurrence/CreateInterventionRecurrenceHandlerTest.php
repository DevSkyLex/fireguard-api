<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Recurrence\CreateInterventionRecurrence;

use DateTimeImmutable;
use Intervention\Application\Contract\Recurrence\InterventionRecurrenceView;
use Intervention\Application\Contract\Template\InterventionTemplateView;
use Intervention\Application\Port\Outbound\{InterventionRecurrencePort, InterventionTemplatePort};
use Intervention\Application\UseCase\Command\Recurrence\CreateInterventionRecurrence\{CreateInterventionRecurrenceCommand, CreateInterventionRecurrenceHandler};
use Intervention\Domain\Event\Recurrence\InterventionRecurrenceCreatedEvent;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException, InterventionValidationException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

use function compact;
use function sprintf;

/**
 * Test CreateInterventionRecurrenceHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateInterventionRecurrenceHandler::class)]
final class CreateInterventionRecurrenceHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e01';

  private const string OTHER_ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e09';

  private const string TEMPLATE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e02';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e03';

  #[Test]
  public function itRejectsAUserMissingThePlanPermission(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->expects(self::never())->method('find');

    $this->expectException(InterventionAccessDeniedException::class);

    $this->handler($templates, $this->createStub(InterventionRecurrencePort::class), $authorization)(self::command());
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->expects(self::never())->method('find');

    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage(sprintf('Organization with ID "%s" not found.', self::ORGANIZATION_ID));

    $this->handler($templates, $this->createStub(InterventionRecurrencePort::class), $authorization)(self::command());
  }

  #[Test]
  public function itThrowsWhenTheTemplateCannotBeFound(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(null);

    $this->expectException(InterventionNotFoundException::class);

    $this->handler($templates, $this->createStub(InterventionRecurrencePort::class), $this->grantedAuthorization())(self::command());
  }

  #[Test]
  public function itThrowsWhenTheTemplateBelongsToAnotherOrganization(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template(self::OTHER_ORGANIZATION_ID));

    $this->expectException(InterventionNotFoundException::class);

    $this->handler($templates, $this->createStub(InterventionRecurrencePort::class), $this->grantedAuthorization())(self::command());
  }

  #[Test]
  public function itRejectsABlankName(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template(self::ORGANIZATION_ID));

    $this->expectException(InterventionValidationException::class);

    $this->handler($templates, $this->createStub(InterventionRecurrencePort::class), $this->grantedAuthorization())(
      self::command(name: '   '),
    );
  }

  #[Test]
  public function itRejectsALeadTimeOutsideBounds(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template(self::ORGANIZATION_ID));

    $this->expectException(InterventionValidationException::class);

    $this->handler($templates, $this->createStub(InterventionRecurrencePort::class), $this->grantedAuthorization())(
      self::command(leadTimeDays: 91),
    );
  }

  #[Test]
  public function itRejectsAnIntervalOutsideBounds(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template(self::ORGANIZATION_ID));

    $this->expectException(InterventionValidationException::class);

    $this->handler($templates, $this->createStub(InterventionRecurrencePort::class), $this->grantedAuthorization())(
      self::command(interval: 13),
    );
  }

  #[Test]
  public function itComputesTheInitialNextOccurrenceAndDispatchesTheCreatedEvent(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template(self::ORGANIZATION_ID));

    $captured = [];
    $recurrences = $this->createMock(InterventionRecurrencePort::class);
    $recurrences->expects(self::once())
      ->method('create')
      ->willReturnCallback(function (
        string $organizationId,
        string $templateId,
        string $name,
        ?string $siteId,
        ?string $responsibleId,
        string $frequency,
        int $interval,
        DateTimeImmutable $anchorDate,
        string $timezone,
        int $leadTimeDays,
        DateTimeImmutable $nextOccurrenceAt,
        ?DateTimeImmutable $endAt,
      ) use (&$captured): InterventionRecurrenceView {
        $captured = compact(
          'organizationId',
          'templateId',
          'name',
          'siteId',
          'responsibleId',
          'frequency',
          'interval',
          'anchorDate',
          'timezone',
          'leadTimeDays',
          'nextOccurrenceAt',
          'endAt',
        );

        return $this->recurrenceView();
      });

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (InterventionRecurrenceCreatedEvent $event): bool {
        self::assertSame(self::ORGANIZATION_ID, $event->organizationId);
        self::assertSame(self::TEMPLATE_ID, $event->templateId);
        self::assertSame(self::USER_ID, $event->actorUserId);

        return true;
      }));

    $result = $this->handler($templates, $recurrences, $this->grantedAuthorization(), $eventDispatcher)(self::command());

    self::assertSame(self::ORGANIZATION_ID, $captured['organizationId']);
    self::assertSame(self::TEMPLATE_ID, $captured['templateId']);
    self::assertSame('monthly', $captured['frequency']);
    self::assertSame(1, $captured['interval']);
    // "now" is fixed at 2026-01-05 by the stubbed clock; the anchor (2026-01-01)
    // walked monthly lands on 2026-02-01, the first occurrence strictly after now.
    $nextOccurrenceAt = $captured['nextOccurrenceAt'];
    self::assertInstanceOf(DateTimeImmutable::class, $nextOccurrenceAt);
    self::assertSame('2026-02-01T00:00:00+00:00', $nextOccurrenceAt->format('c'));
    self::assertInstanceOf(InterventionRecurrenceView::class, $result->recurrence);
  }

  private static function command(string $name = 'Quarterly audit', int $leadTimeDays = 7, int $interval = 1): CreateInterventionRecurrenceCommand
  {
    return new CreateInterventionRecurrenceCommand(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      templateId: self::TEMPLATE_ID,
      name: $name,
      siteId: null,
      responsibleId: null,
      frequency: 'monthly',
      interval: $interval,
      anchorDate: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      timezone: 'UTC',
      leadTimeDays: $leadTimeDays,
    );
  }

  private function template(string $organizationId): InterventionTemplateView
  {
    return new InterventionTemplateView(
      self::TEMPLATE_ID,
      $organizationId,
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
    );
  }

  private function recurrenceView(): InterventionRecurrenceView
  {
    return new InterventionRecurrenceView(
      'recurrence-1',
      self::ORGANIZATION_ID,
      self::TEMPLATE_ID,
      'Quarterly audit',
      null,
      null,
      'monthly',
      1,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      'UTC',
      7,
      new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      null,
      true,
      null,
      new DateTimeImmutable(),
      new DateTimeImmutable(),
    );
  }

  private function handler(
    InterventionTemplatePort $templates,
    InterventionRecurrencePort $recurrences,
    OrganizationAuthorizationPort $authorization,
    ?EventDispatcherPort $eventDispatcher = null,
  ): CreateInterventionRecurrenceHandler {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-05T00:00:00+00:00'));

    return new CreateInterventionRecurrenceHandler(
      $recurrences,
      $templates,
      $authorization,
      $clock,
      $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
    );
  }

  private function grantedAuthorization(): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    return $authorization;
  }
}
