<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Recurrence\DeleteInterventionRecurrence;

use DateTimeImmutable;
use Intervention\Application\Contract\Recurrence\InterventionRecurrenceView;
use Intervention\Application\Port\Outbound\InterventionRecurrencePort;
use Intervention\Application\UseCase\Command\Recurrence\DeleteInterventionRecurrence\{DeleteInterventionRecurrenceCommand, DeleteInterventionRecurrenceHandler};
use Intervention\Domain\Event\Recurrence\InterventionRecurrenceDeletedEvent;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\VoidResult;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test DeleteInterventionRecurrenceHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteInterventionRecurrenceHandler::class)]
final class DeleteInterventionRecurrenceHandlerTest extends TestCase
{
  private const string RECURRENCE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e01';

  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e02';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63e03';

  #[Test]
  public function itThrowsWhenTheRecurrenceCannotBeFound(): void
  {
    $recurrences = $this->createMock(InterventionRecurrencePort::class);
    $recurrences->method('find')->willReturn(null);
    $recurrences->expects(self::never())->method('delete');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new DeleteInterventionRecurrenceHandler($recurrences, $authorization, $eventDispatcher);

    $this->expectException(InterventionNotFoundException::class);

    $handler(self::command());
  }

  #[Test]
  public function itRejectsAUserMissingThePlanPermission(): void
  {
    $recurrences = $this->createMock(InterventionRecurrencePort::class);
    $recurrences->method('find')->willReturn($this->recurrenceView());
    $recurrences->expects(self::never())->method('delete');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new DeleteInterventionRecurrenceHandler($recurrences, $authorization, $eventDispatcher);

    $this->expectException(InterventionAccessDeniedException::class);

    $handler(self::command());
  }

  #[Test]
  public function itDeletesTheRecurrenceAndDispatchesTheDeletedEvent(): void
  {
    $recurrences = $this->createMock(InterventionRecurrencePort::class);
    $recurrences->method('find')->willReturn($this->recurrenceView());
    $recurrences->expects(self::once())->method('delete')->with(self::RECURRENCE_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (InterventionRecurrenceDeletedEvent $event): bool {
        self::assertSame(self::ORGANIZATION_ID, $event->organizationId);
        self::assertSame(self::RECURRENCE_ID, $event->recurrenceId);
        self::assertSame(self::USER_ID, $event->actorUserId);

        return true;
      }));

    $handler = new DeleteInterventionRecurrenceHandler($recurrences, $authorization, $eventDispatcher);

    $result = $handler(self::command());

    self::assertInstanceOf(VoidResult::class, $result);
  }

  private static function command(): DeleteInterventionRecurrenceCommand
  {
    return new DeleteInterventionRecurrenceCommand(
      userId: self::USER_ID,
      recurrenceId: self::RECURRENCE_ID,
    );
  }

  private function recurrenceView(): InterventionRecurrenceView
  {
    return new InterventionRecurrenceView(
      self::RECURRENCE_ID,
      self::ORGANIZATION_ID,
      '018f0b68-6758-7a12-8a1d-3f0d97f63e04',
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
}
