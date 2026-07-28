<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Presentation\Api\Processor\Event;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use Calendar\Application\UseCase\Command\Event\UpdateCalendarEvent\{UpdateCalendarEventCommand, UpdateCalendarEventResult};
use Calendar\Domain\Exception\CalendarEventValidationException;
use Calendar\Presentation\Api\Dto\Input\Event\UpdateCalendarEventInput;
use Calendar\Presentation\Api\Factory\CalendarEventOutputFactory;
use Calendar\Presentation\Api\Processor\Event\UpdateCalendarEventProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\MergePatchFields;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, UnprocessableEntityHttpException};

use function json_encode;

/**
 * Test UpdateCalendarEventProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateCalendarEventProcessor::class)]
final class UpdateCalendarEventProcessorTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string EVENT_ID = 'event-1';

  private const string USER_ID = 'user-id';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessDispatchesAPartialUpdateWithPresenceFlags(): void
  {
    $input = new UpdateCalendarEventInput();
    $input->title = 'Rescheduled drill';
    $input->startsAt = '2026-05-01T09:00:00+00:00';

    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (UpdateCalendarEventCommand $command) use (&$captured): UpdateCalendarEventResult {
        $captured = $command;

        return $this->updateResult();
      });

    $processor = new UpdateCalendarEventProcessor(
      $commandBus,
      $this->authenticatedSecurity(),
      new CalendarEventOutputFactory(),
      $this->mergePatchFields(['title' => 'Rescheduled drill', 'startsAt' => '2026-05-01T09:00:00+00:00']),
    );

    $output = $processor->process($input, new Patch(), [
      'organizationId' => self::ORGANIZATION_ID,
      'eventId' => self::EVENT_ID,
    ]);

    self::assertInstanceOf(UpdateCalendarEventCommand::class, $captured);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame(self::EVENT_ID, $captured->eventId);
    self::assertSame(self::USER_ID, $captured->actorUserId);
    self::assertSame('Rescheduled drill', $captured->title);
    self::assertInstanceOf(DateTimeImmutable::class, $captured->startsAt);
    self::assertSame('2026-05-01T09:00:00+00:00', $captured->startsAt->format('c'));
    self::assertNull($captured->endsAt);

    self::assertTrue($captured->hasTitle);
    self::assertTrue($captured->hasStartsAt);
    self::assertFalse($captured->hasDescription);
    self::assertFalse($captured->hasEndsAt);
    self::assertFalse($captured->hasAllDay);
    self::assertFalse($captured->hasFacilityId);

    self::assertSame(self::EVENT_ID, $output->id);
    self::assertSame('Rescheduled drill', $output->title);
  }

  #[Test]
  public function testProcessMarksExplicitNullsAsPresent(): void
  {
    $input = new UpdateCalendarEventInput();

    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (UpdateCalendarEventCommand $command) use (&$captured): UpdateCalendarEventResult {
        $captured = $command;

        return $this->updateResult();
      });

    $processor = new UpdateCalendarEventProcessor(
      $commandBus,
      $this->authenticatedSecurity(),
      new CalendarEventOutputFactory(),
      $this->mergePatchFields(['description' => null, 'facilityId' => null]),
    );

    $processor->process($input, new Patch(), [
      'organizationId' => self::ORGANIZATION_ID,
      'eventId' => self::EVENT_ID,
    ]);

    self::assertInstanceOf(UpdateCalendarEventCommand::class, $captured);
    self::assertTrue($captured->hasDescription);
    self::assertTrue($captured->hasFacilityId);
    self::assertFalse($captured->hasTitle);
    self::assertNull($captured->description);
    self::assertNull($captured->startsAt);
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new UpdateCalendarEventProcessor(
      $this->createStub(CommandBusPort::class),
      $security,
      new CalendarEventOutputFactory(),
      $this->mergePatchFields([]),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new UpdateCalendarEventInput(), new Patch(), [
      'organizationId' => self::ORGANIZATION_ID,
      'eventId' => self::EVENT_ID,
    ]);
  }

  #[Test]
  public function testProcessRequiresBothUriVariables(): void
  {
    $processor = new UpdateCalendarEventProcessor(
      $this->createStub(CommandBusPort::class),
      $this->authenticatedSecurity(),
      new CalendarEventOutputFactory(),
      $this->mergePatchFields([]),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new UpdateCalendarEventInput(), new Patch(), ['eventId' => self::EVENT_ID]);
  }

  #[Test]
  public function testProcessMapsDomainExceptions(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(CalendarEventValidationException::endBeforeStart());

    $processor = new UpdateCalendarEventProcessor(
      $commandBus,
      $this->authenticatedSecurity(),
      new CalendarEventOutputFactory(),
      $this->mergePatchFields([]),
    );

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process(new UpdateCalendarEventInput(), new Patch(), [
      'organizationId' => self::ORGANIZATION_ID,
      'eventId' => self::EVENT_ID,
    ]);
  }
  // #endregion

  // #region Helpers
  private function authenticatedSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return $security;
  }

  /**
   * @param array<string, mixed> $fields
   */
  private function mergePatchFields(array $fields): MergePatchFields
  {
    $stack = new RequestStack();
    $stack->push(Request::create(
      '/calendar-events/' . self::EVENT_ID,
      'PATCH',
      content: (string) json_encode($fields),
    ));

    return new MergePatchFields($stack);
  }

  private function updateResult(): UpdateCalendarEventResult
  {
    return new UpdateCalendarEventResult(
      id: self::EVENT_ID,
      organizationId: self::ORGANIZATION_ID,
      title: 'Rescheduled drill',
      description: null,
      startsAt: new DateTimeImmutable('2026-05-01T09:00:00+00:00'),
      endsAt: null,
      allDay: false,
      facilityId: null,
      createdByMemberId: self::USER_ID,
      createdAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-04-01T09:00:00+00:00'),
    );
  }
  // #endregion
}
