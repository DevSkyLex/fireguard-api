<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Recurrence\UpdateInterventionRecurrence;

use DateTimeImmutable;
use Intervention\Application\Contract\Recurrence\InterventionRecurrenceView;
use Intervention\Application\Port\Outbound\InterventionRecurrencePort;
use Intervention\Application\UseCase\Command\Recurrence\UpdateInterventionRecurrence\{UpdateInterventionRecurrenceCommand, UpdateInterventionRecurrenceHandler};
use Intervention\Domain\Event\Recurrence\InterventionRecurrenceUpdatedEvent;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException, InterventionValidationException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

use function compact;

/**
 * Test UpdateInterventionRecurrenceHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateInterventionRecurrenceHandler::class)]
final class UpdateInterventionRecurrenceHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63d01';

  private const string TEMPLATE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63d02';

  private const string RECURRENCE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63d03';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63d04';

  #[Test]
  public function itThrowsWhenTheRecurrenceCannotBeFound(): void
  {
    $recurrences = $this->createStub(InterventionRecurrencePort::class);
    $recurrences->method('find')->willReturn(null);

    $this->expectException(InterventionNotFoundException::class);

    $this->handler($recurrences, $this->grantedAuthorization())(self::command());
  }

  #[Test]
  public function itRejectsAUserMissingThePlanPermission(): void
  {
    $recurrences = $this->createStub(InterventionRecurrencePort::class);
    $recurrences->method('find')->willReturn($this->existingRecurrence());
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $this->expectException(InterventionAccessDeniedException::class);

    $this->handler($recurrences, $authorization)(self::command());
  }

  #[Test]
  public function itRejectsABlankName(): void
  {
    $recurrences = $this->createStub(InterventionRecurrencePort::class);
    $recurrences->method('find')->willReturn($this->existingRecurrence());

    $this->expectException(InterventionValidationException::class);

    $this->handler($recurrences, $this->grantedAuthorization())(
      self::command(name: '   ', hasName: true),
    );
  }

  #[Test]
  public function itRejectsALeadTimeOutsideBounds(): void
  {
    $recurrences = $this->createStub(InterventionRecurrencePort::class);
    $recurrences->method('find')->willReturn($this->existingRecurrence());

    $this->expectException(InterventionValidationException::class);

    $this->handler($recurrences, $this->grantedAuthorization())(
      self::command(leadTimeDays: 91, hasLeadTimeDays: true),
    );
  }

  #[Test]
  public function itRejectsAMalformedTimezone(): void
  {
    $recurrences = $this->createStub(InterventionRecurrencePort::class);
    $recurrences->method('find')->willReturn($this->existingRecurrence());

    $this->expectException(InterventionValidationException::class);

    $this->handler($recurrences, $this->grantedAuthorization())(
      self::command(timezone: 'Definitely/NotAZone', hasTimezone: true),
    );
  }

  #[Test]
  public function itPatchesOnlyPresentFieldsAndLeavesTheScheduleUntouchedWhenNoRuleFieldChanges(): void
  {
    $captured = [];
    $updated = $this->existingRecurrence();
    $recurrences = $this->recordingRecurrences($this->existingRecurrence(), $updated, $captured);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (InterventionRecurrenceUpdatedEvent $event): bool {
        self::assertSame(self::ORGANIZATION_ID, $event->organizationId);
        self::assertSame(self::RECURRENCE_ID, $event->recurrenceId);
        self::assertSame(self::USER_ID, $event->actorUserId);

        return true;
      }));

    $result = $this->handler($recurrences, $this->grantedAuthorization(), $eventDispatcher)(
      self::command(name: 'Renamed recurrence', hasName: true),
    );

    self::assertSame(self::RECURRENCE_ID, $captured['id']);
    self::assertSame('Renamed recurrence', $captured['name']);
    self::assertTrue($captured['hasName']);
    self::assertFalse($captured['hasFrequency']);
    self::assertFalse($captured['hasInterval']);
    // No rule-affecting field changed, so the stored schedule is left untouched.
    self::assertNull($captured['nextOccurrenceAt']);
    self::assertFalse($captured['hasNextOccurrenceAt']);
    self::assertSame($updated, $result->recurrence);
  }

  #[Test]
  public function itRecomputesTheNextOccurrenceWhenARuleFieldChanges(): void
  {
    $captured = [];
    $updated = $this->existingRecurrence();
    $recurrences = $this->recordingRecurrences($this->existingRecurrence(), $updated, $captured);

    $result = $this->handler($recurrences, $this->grantedAuthorization())(
      self::command(interval: 2, hasInterval: true),
    );

    self::assertSame(2, $captured['interval']);
    self::assertTrue($captured['hasInterval']);
    // "now" is fixed at 2026-01-05 by the stubbed clock; the anchor (2026-01-01)
    // walked every two months lands on 2026-03-01, the first occurrence strictly
    // after now, and the recomputed value is flagged as changed.
    $nextOccurrenceAt = $captured['nextOccurrenceAt'];
    self::assertInstanceOf(DateTimeImmutable::class, $nextOccurrenceAt);
    self::assertSame('2026-03-01T00:00:00+00:00', $nextOccurrenceAt->format('c'));
    self::assertTrue($captured['hasNextOccurrenceAt']);
    self::assertSame($updated, $result->recurrence);
  }

  /**
   * @param array<string, mixed> $captured
   */
  private function recordingRecurrences(
    InterventionRecurrenceView $existing,
    InterventionRecurrenceView $updated,
    array &$captured,
  ): InterventionRecurrencePort {
    $recurrences = $this->createMock(InterventionRecurrencePort::class);
    $recurrences->method('find')->willReturn($existing);
    $recurrences->expects(self::once())
      ->method('update')
      ->willReturnCallback(function (
        string $id,
        ?string $name,
        ?string $siteId,
        ?string $responsibleId,
        ?string $frequency,
        ?int $interval,
        ?DateTimeImmutable $anchorDate,
        ?string $timezone,
        ?int $leadTimeDays,
        ?DateTimeImmutable $nextOccurrenceAt,
        ?DateTimeImmutable $endAt,
        ?bool $isActive,
        bool $hasName,
        bool $hasSiteId,
        bool $hasResponsibleId,
        bool $hasFrequency,
        bool $hasInterval,
        bool $hasAnchorDate,
        bool $hasTimezone,
        bool $hasLeadTimeDays,
        bool $hasNextOccurrenceAt,
        bool $hasEndAt,
        bool $hasIsActive,
      ) use (&$captured, $updated): InterventionRecurrenceView {
        $captured = compact(
          'id',
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
          'isActive',
          'hasName',
          'hasSiteId',
          'hasResponsibleId',
          'hasFrequency',
          'hasInterval',
          'hasAnchorDate',
          'hasTimezone',
          'hasLeadTimeDays',
          'hasNextOccurrenceAt',
          'hasEndAt',
          'hasIsActive',
        );

        return $updated;
      });

    return $recurrences;
  }

  private static function command(
    ?string $name = null,
    ?int $interval = null,
    ?string $timezone = null,
    ?int $leadTimeDays = null,
    bool $hasName = false,
    bool $hasInterval = false,
    bool $hasTimezone = false,
    bool $hasLeadTimeDays = false,
  ): UpdateInterventionRecurrenceCommand {
    return new UpdateInterventionRecurrenceCommand(
      userId: self::USER_ID,
      recurrenceId: self::RECURRENCE_ID,
      name: $name,
      interval: $interval,
      timezone: $timezone,
      leadTimeDays: $leadTimeDays,
      hasName: $hasName,
      hasInterval: $hasInterval,
      hasTimezone: $hasTimezone,
      hasLeadTimeDays: $hasLeadTimeDays,
    );
  }

  private function existingRecurrence(): InterventionRecurrenceView
  {
    return new InterventionRecurrenceView(
      self::RECURRENCE_ID,
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
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }

  private function handler(
    InterventionRecurrencePort $recurrences,
    OrganizationAuthorizationPort $authorization,
    ?EventDispatcherPort $eventDispatcher = null,
  ): UpdateInterventionRecurrenceHandler {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-05T00:00:00+00:00'));

    return new UpdateInterventionRecurrenceHandler(
      $recurrences,
      $authorization,
      $clock,
      $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
    );
  }

  private function grantedAuthorization(): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    return $authorization;
  }
}
