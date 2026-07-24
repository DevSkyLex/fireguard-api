<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Presentation\Api\Processor\Event;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Calendar\Application\UseCase\Command\Event\CreateCalendarEvent\{CreateCalendarEventCommand, CreateCalendarEventResult};
use Calendar\Presentation\Api\Dto\Input\Event\CreateCalendarEventInput;
use Calendar\Presentation\Api\Dto\Output\Event\CalendarEventOutput;
use Calendar\Presentation\Api\Factory\CalendarEventOutputFactory;
use Calendar\Presentation\Api\Processor\Event\CreateCalendarEventProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

/**
 * Test CreateCalendarEventProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateCalendarEventProcessor::class)]
final class CreateCalendarEventProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProcessDispatchesTheCommandAndReturnsTheEventOutput(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $input = new CreateCalendarEventInput();
    $input->title = 'Fire drill';
    $input->description = 'Quarterly fire drill';
    $input->startsAt = '2026-08-01T09:00:00+02:00';
    $input->endsAt = '2026-08-01T11:00:00+02:00';
    $input->allDay = false;
    $input->facilityId = null;

    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (CreateCalendarEventCommand $command) use (&$captured): CreateCalendarEventResult {
        $captured = $command;

        return new CreateCalendarEventResult(
          id: 'event-1',
          organizationId: self::ORGANIZATION_ID,
          title: 'Fire drill',
          description: 'Quarterly fire drill',
          startsAt: new DateTimeImmutable('2026-08-01T09:00:00+02:00'),
          endsAt: new DateTimeImmutable('2026-08-01T11:00:00+02:00'),
          allDay: false,
          facilityId: null,
          createdByMemberId: 'member-1',
          createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
          updatedAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
        );
      });

    $processor = new CreateCalendarEventProcessor($commandBus, $security, new CalendarEventOutputFactory());

    $output = $processor->process($input, new Post(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(CreateCalendarEventCommand::class, $captured);
    self::assertSame(self::USER_ID, $captured->actorUserId);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame('Fire drill', $captured->title);
    self::assertEquals(new DateTimeImmutable('2026-08-01T09:00:00+02:00'), $captured->startsAt);

    self::assertInstanceOf(CalendarEventOutput::class, $output);
    self::assertSame('event-1', $output->id);
    self::assertSame('Fire drill', $output->title);
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new CreateCalendarEventProcessor(
      $this->createStub(CommandBusPort::class),
      $security,
      new CalendarEventOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new CreateCalendarEventInput(), new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessRequiresAnOrganizationIdUriVariable(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $processor = new CreateCalendarEventProcessor(
      $this->createStub(CommandBusPort::class),
      $security,
      new CalendarEventOutputFactory(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new CreateCalendarEventInput(), new Post(), []);
  }
}
