<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Presentation\Api\Processor\Notification;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use Notification\Application\UseCase\Command\Notification\MarkAllNotificationsAsRead\{MarkAllNotificationsAsReadCommand, MarkAllNotificationsAsReadResult};
use Notification\Presentation\Api\Dto\Output\Notification\MarkAllNotificationsAsReadOutput;
use Notification\Presentation\Api\Processor\Notification\MarkAllNotificationsAsReadProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(MarkAllNotificationsAsReadProcessor::class)]
final class MarkAllNotificationsAsReadProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $processor = new MarkAllNotificationsAsReadProcessor(
      commandBus: $commandBus,
      security: $security,
      requestStack: $requestStack,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new stdClass(), new Patch());
  }

  #[Test]
  public function testProcessDispatchesCommandAndReturnsAffectedCount(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442800'));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (MarkAllNotificationsAsReadCommand $command): bool {
        return '550e8400-e29b-41d4-a716-446655442800' === $command->userId
          && null === $command->organizationId;
      }))
      ->willReturn(new MarkAllNotificationsAsReadResult(count: 3));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $processor = new MarkAllNotificationsAsReadProcessor(
      commandBus: $commandBus,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $processor->process(new stdClass(), new Patch());

    self::assertInstanceOf(MarkAllNotificationsAsReadOutput::class, $output);
    self::assertSame(3, $output->count);
  }

  #[Test]
  public function testProcessPassesOrganizationQueryFilterToCommand(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442801'));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (MarkAllNotificationsAsReadCommand $command): bool {
        return '550e8400-e29b-41d4-a716-446655442899' === $command->organizationId;
      }))
      ->willReturn(new MarkAllNotificationsAsReadResult(count: 0));

    $request = new Request();
    $request->query->set('organization', '550e8400-e29b-41d4-a716-446655442899');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $processor = new MarkAllNotificationsAsReadProcessor(
      commandBus: $commandBus,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $processor->process(new stdClass(), new Patch());

    self::assertSame(0, $output->count, 'idempotent: zero rows affected is a valid, non-error outcome');
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
