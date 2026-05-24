<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Presentation\Api\Processor\Session;

use ApiPlatform\Metadata\Post;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Session\Application\UseCase\Command\Session\RevokeAllUserSessions\RevokeAllUserSessionsCommand;
use Session\Presentation\Api\Processor\Session\RevokeAllSessionsProcessor;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test RevokeAllSessionsProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RevokeAllSessionsProcessor::class)]
final class RevokeAllSessionsProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new RevokeAllSessionsProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
    );

    $this->expectException(UnauthorizedHttpException::class);

    $processor->process(null, new Post());
  }

  #[Test]
  public function testProcessDispatchesCommand(): void
  {
    $user = $this->createMock(UserInterface::class);
    $user->expects(self::once())
      ->method('getUserIdentifier')
      ->willReturn('user-1');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        fn (RevokeAllUserSessionsCommand $command) => 'user-1' === $command->userId,
      ));

    $processor = new RevokeAllSessionsProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $processor->process(null, new Post());
  }
  // #endregion
}
