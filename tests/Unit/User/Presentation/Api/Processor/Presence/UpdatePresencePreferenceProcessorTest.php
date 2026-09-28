<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Processor\Presence;

use ApiPlatform\Metadata\Patch;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use User\Application\UseCase\Command\Presence\UpdatePresencePreference\{UpdatePresencePreferenceCommand, UpdatePresencePreferenceResult};
use User\Presentation\Api\Dto\Input\Presence\UpdatePresencePreferenceInput;
use User\Presentation\Api\Processor\Presence\UpdatePresencePreferenceProcessor;

/**
 * Test UpdatePresencePreferenceProcessorTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdatePresencePreferenceProcessorTest extends TestCase
{
  #[Test]
  /**
   * @since 1.0.0
   */
  public function savesCurrentActorPreferenceWithoutProfilePermission(): void
  {
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('user');
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (UpdatePresencePreferenceCommand $command): bool => 'user' === $command->userId && $command->doNotDisturb))->willReturn(new UpdatePresencePreferenceResult(true, 2));
    $input = new UpdatePresencePreferenceInput();
    $input->doNotDisturb = true;
    $result = new UpdatePresencePreferenceProcessor($bus, $actor)->process($input, new Patch());
    self::assertTrue($result->doNotDisturb);
    self::assertSame(2, $result->revision);
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function missingBooleanDoesNotChangePreference(): void
  {
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('user');
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $this->expectException(BadRequestHttpException::class);
    new UpdatePresencePreferenceProcessor($bus, $actor)->process(new UpdatePresencePreferenceInput(), new Patch());
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function anonymousDoesNotReachCommand(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $this->expectException(AccessDeniedHttpException::class);
    new UpdatePresencePreferenceProcessor($bus, $this->createStub(CurrentActorPort::class))->process(new UpdatePresencePreferenceInput(), new Patch());
  }
}
