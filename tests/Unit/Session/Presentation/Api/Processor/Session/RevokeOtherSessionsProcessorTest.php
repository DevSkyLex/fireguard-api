<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Presentation\Api\Processor\Session;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Session\Application\UseCase\Command\Session\RevokeOtherUserSessions\{RevokeOtherUserSessionsCommand, RevokeOtherUserSessionsResult};
use Session\Presentation\Api\Dto\Output\Session\RevokeOtherSessionsOutput;
use Session\Presentation\Api\Processor\Session\RevokeOtherSessionsProcessor;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Test RevokeOtherSessionsProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RevokeOtherSessionsProcessor::class)]
final class RevokeOtherSessionsProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new RevokeOtherSessionsProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(UnauthorizedHttpException::class);

    $processor->process(null, new Post());
  }

  #[Test]
  public function testProcessDispatchesCommandWithResolvedCurrentSessionIdAndReturnsCount(): void
  {
    $user = new SecurityUser(
      id: 'user-1',
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $request = new Request();
    $session = new Session(new MockArraySessionStorage());
    $session->setId('mock-session-id');
    $request->setSession($session);
    $currentSessionId = $request->getSession()->getId();

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        fn (RevokeOtherUserSessionsCommand $command) => 'user-1' === $command->userId
          && $currentSessionId === $command->currentSessionId,
      ))
      ->willReturn(new RevokeOtherUserSessionsResult(revokedCount: 3));

    $processor = new RevokeOtherSessionsProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $output = $processor->process(null, new Post(), [], ['request' => $request]);

    self::assertInstanceOf(RevokeOtherSessionsOutput::class, $output);
    self::assertSame(3, $output->revokedCount);
  }

  #[Test]
  public function testProcessIsIdempotentWhenNothingLeftToRevoke(): void
  {
    $user = new SecurityUser(
      id: 'user-1',
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(new RevokeOtherUserSessionsResult(revokedCount: 0));

    $processor = new RevokeOtherSessionsProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $output = $processor->process(null, new Post(), [], []);

    self::assertSame(0, $output->revokedCount, 'idempotent: zero sessions revoked is a valid, non-error outcome');
  }
  // #endregion
}
