<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Presentation\Api\Processor\Notification;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Notification\Application\UseCase\Command\Notification\MarkNotificationAsRead\{MarkNotificationAsReadCommand, MarkNotificationAsReadResult};
use Notification\Presentation\Api\Dto\Output\Notification\NotificationOutput;
use Notification\Presentation\Api\Processor\Notification\MarkNotificationAsReadProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\Exception\InvalidValueException;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

#[CoversClass(MarkNotificationAsReadProcessor::class)]
final class MarkNotificationAsReadProcessorTest extends TestCase
{
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

    $processor = new MarkNotificationAsReadProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new stdClass(), new Patch(), ['id' => '550e8400-e29b-41d4-a716-446655442401']);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442400'));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new MarkNotificationAsReadProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(new stdClass(), new Patch(), ['id' => '']);
  }

  #[Test]
  public function testProcessDispatchesCommandAndMapsOutput(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442400'));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (MarkNotificationAsReadCommand $command): bool {
        return '550e8400-e29b-41d4-a716-446655442400' === $command->userId
          && '550e8400-e29b-41d4-a716-446655442401' === $command->notificationId;
      }))
      ->willReturn(new MarkNotificationAsReadResult(
        id: '550e8400-e29b-41d4-a716-446655442401',
        type: 'organization.invitation',
        subject: 'Invitation',
        body: '<p>Body</p>',
        channels: ['email'],
        payload: ['organizationName' => 'Fireguard HQ'],
        isRead: true,
        createdAt: new DateTimeImmutable('2026-02-11T10:00:00+00:00'),
        readAt: new DateTimeImmutable('2026-02-11T11:00:00+00:00'),
      ));

    $processor = new MarkNotificationAsReadProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $output = $processor->process(
      data: new stdClass(),
      operation: new Patch(),
      uriVariables: ['id' => '550e8400-e29b-41d4-a716-446655442401'],
      context: [],
    );

    self::assertInstanceOf(NotificationOutput::class, $output);
    self::assertSame('550e8400-e29b-41d4-a716-446655442401', $output->id);
    self::assertTrue($output->isRead);
    self::assertNotNull($output->readAt);
  }

  // The test that fed a nested exception here to prove the processor unwrapped
  // and mapped it is gone with the mapping. Unwrapping is one behaviour in one
  // place now — BusFailureUnwrappingSubscriberTest — and the status comes from
  // `exception_to_status`.

  #[Test]
  public function testProcessRethrowsNestedInvalidValueException(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442400'));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException(
        'wrapped',
        0,
        InvalidValueException::because('Invalid UUID provided.'),
      ));

    $processor = new MarkNotificationAsReadProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(RuntimeException::class);

    $processor->process(
      data: new stdClass(),
      operation: new Patch(),
      uriVariables: ['id' => 'not-a-uuid'],
      context: [],
    );
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
