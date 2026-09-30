<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Command\Presence\UpdatePresencePreference;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort};
use User\Application\Contract\Presence\{PresencePreference, PresencePreferenceChangedEvent, PresencePreferenceWrite};
use User\Application\Port\Outbound\PresencePreferenceRepositoryPort;
use User\Application\UseCase\Command\Presence\UpdatePresencePreference\{UpdatePresencePreferenceCommand, UpdatePresencePreferenceHandler};

/**
 * Test UpdatePresencePreferenceHandlerTest.
 *
 * @category Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdatePresencePreferenceHandlerTest extends TestCase
{
  #[Test]
  public function enablingInvisibleAtomicallyClearsNpd(): void
  {
    $repository = $this->createMock(PresencePreferenceRepositoryPort::class);
    $repository->expects(self::once())->method('save')->with('user', false, true)
      ->willReturn(new PresencePreferenceWrite(new PresencePreference(false, 2, true), true));
    $result = new UpdatePresencePreferenceHandler($repository, $this->createStub(EventDispatcherPort::class), $this->createStub(LoggerPort::class))(new UpdatePresencePreferenceCommand('user', null, true));
    self::assertFalse($result->doNotDisturb);
    self::assertTrue($result->invisible);
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function emitsCommittedRevisionAfterSaveAndKeepsSuccessWhenDeliveryFails(): void
  {
    $saved = false;
    $repository = $this->createMock(PresencePreferenceRepositoryPort::class);
    $repository->expects(self::once())->method('save')->with('user', true, false)->willReturnCallback(static function () use (&$saved): PresencePreferenceWrite {
      $saved = true;

      return new PresencePreferenceWrite(new PresencePreference(true, 2), true);
    });
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->willReturnCallback(static function (PresencePreferenceChangedEvent $event) use (&$saved): void {
      self::assertTrue($saved);
      self::assertSame('user', $event->userId);
      self::assertTrue($event->doNotDisturb);
      self::assertSame(2, $event->revision);

      throw new RuntimeException('Hub unavailable');
    });
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('warning');
    $result = new UpdatePresencePreferenceHandler($repository, $events, $logger)(new UpdatePresencePreferenceCommand('user', true));
    self::assertTrue($result->doNotDisturb);
    self::assertSame(2, $result->revision);
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function idempotentSaveDoesNotEmitEvent(): void
  {
    $repository = $this->createStub(PresencePreferenceRepositoryPort::class);
    $repository->method('save')->willReturn(new PresencePreferenceWrite(new PresencePreference(true, 4), false));
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $result = new UpdatePresencePreferenceHandler($repository, $events, $this->createStub(LoggerPort::class))(new UpdatePresencePreferenceCommand('user', true));
    self::assertSame(4, $result->revision);
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function persistenceFailureDoesNotEmitEvent(): void
  {
    $repository = $this->createStub(PresencePreferenceRepositoryPort::class);
    $repository->method('save')->willThrowException(new RuntimeException('Database unavailable'));
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $this->expectException(RuntimeException::class);
    new UpdatePresencePreferenceHandler($repository, $events, $this->createStub(LoggerPort::class))(new UpdatePresencePreferenceCommand('user', true));
  }
}
