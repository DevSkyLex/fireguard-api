<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Presentation\Api\Processor\Event;

use ApiPlatform\Metadata\Delete;
use Auth\Infrastructure\Security\User\SecurityUser;
use Calendar\Application\UseCase\Command\Event\DeleteCalendarEvent\DeleteCalendarEventCommand;
use Calendar\Domain\Exception\CalendarEventNotFoundException;
use Calendar\Presentation\Api\Processor\Event\DeleteCalendarEventProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test DeleteCalendarEventProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteCalendarEventProcessor::class)]
final class DeleteCalendarEventProcessorTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string EVENT_ID = 'event-1';

  private const string USER_ID = 'user-id';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessDispatchesTheDeleteCommandAndReturnsNull(): void
  {
    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (DeleteCalendarEventCommand $command) use (&$captured): ResultMessage {
        $captured = $command;

        return $this->createStub(ResultMessage::class);
      });

    $processor = new DeleteCalendarEventProcessor($commandBus, $this->authenticatedSecurity());

    $result = $processor->process(null, new Delete(), [
      'organizationId' => self::ORGANIZATION_ID,
      'eventId' => self::EVENT_ID,
    ]);

    self::assertNull($result);
    self::assertInstanceOf(DeleteCalendarEventCommand::class, $captured);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame(self::EVENT_ID, $captured->eventId);
    self::assertSame(self::USER_ID, $captured->actorUserId);
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new DeleteCalendarEventProcessor($this->createStub(CommandBusPort::class), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), [
      'organizationId' => self::ORGANIZATION_ID,
      'eventId' => self::EVENT_ID,
    ]);
  }

  #[Test]
  public function testProcessRequiresBothUriVariables(): void
  {
    $processor = new DeleteCalendarEventProcessor(
      $this->createStub(CommandBusPort::class),
      $this->authenticatedSecurity(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Delete(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessMapsDomainExceptions(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(CalendarEventNotFoundException::withId(self::EVENT_ID));

    $processor = new DeleteCalendarEventProcessor($commandBus, $this->authenticatedSecurity());

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Delete(), [
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
  // #endregion
}
