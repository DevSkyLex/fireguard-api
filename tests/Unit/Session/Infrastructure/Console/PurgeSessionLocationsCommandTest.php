<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Infrastructure\Console;

use PHPUnit\Framework\TestCase;
use Session\Application\UseCase\Command\Session\PurgeSessionLocations\{PurgeSessionLocationsCommand as PurgeLocations, PurgeSessionLocationsResult};
use Session\Infrastructure\Console\PurgeSessionLocationsCommand;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Validates operator scope before any erasure and keeps command output free of personal data.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PurgeSessionLocationsCommandTest extends TestCase
{
  public function testInvalidAccountNeverDispatchesAndDefaultScopeIsRevokedOnly(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(new PurgeLocations())->willReturn(new PurgeSessionLocationsResult(2));
    $tester = new CommandTester(new PurgeSessionLocationsCommand($bus));
    self::assertSame(Command::INVALID, $tester->execute(['--user-id' => 'invalid']));
    self::assertSame(Command::SUCCESS, $tester->execute([]));
    self::assertStringContainsString('2', $tester->getDisplay());
  }

  public function testVerifiedAccountIsScopedWithoutEchoingItsIdentifier(): void
  {
    $id = '123e4567-e89b-12d3-a456-426614174099';
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(new PurgeLocations($id))->willReturn(new PurgeSessionLocationsResult(1));
    $tester = new CommandTester(new PurgeSessionLocationsCommand($bus));
    self::assertSame(Command::SUCCESS, $tester->execute(['--user-id' => $id]));
    self::assertStringNotContainsString($id, $tester->getDisplay());
  }
}
