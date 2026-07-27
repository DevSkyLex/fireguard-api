<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Message;

use ApiPlatform\Metadata\Delete;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\UseCase\Command\Message\DeleteMessage\{
  DeleteMessageCommand,
  DeleteMessageResult
};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Messaging\Presentation\Api\Processor\Message\DeleteMessageProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException
};

/**
 * Test DeleteMessageProcessor.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteMessageProcessor::class)]
final class DeleteMessageProcessorTest extends TestCase
{
  // #region Constants
  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655441700';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessDispatchesTheDeleteCommand(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (DeleteMessageCommand $command): bool => self::USER_ID === $command->userId
        && self::MESSAGE_ID === $command->messageId))
      ->willReturn(new DeleteMessageResult($this->view()));

    $this->createProcessor($commandBus)->process(null, new Delete(), ['id' => self::MESSAGE_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))->process(null, new Delete(), []);
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new DeleteMessageProcessor($this->createStub(CommandBusPort::class), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), ['id' => self::MESSAGE_ID]);
  }

  #[Test]
  public function testProcessMapsNotFoundExceptionToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessagingNotFoundException::message(self::MESSAGE_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process(null, new Delete(), ['id' => self::MESSAGE_ID]);
  }

  #[Test]
  public function testProcessMapsAccessDeniedExceptionToHttp403(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingAccessDeniedException('Not the author.'));

    $this->expectException(AccessDeniedHttpException::class);

    $this->createProcessor($commandBus)->process(null, new Delete(), ['id' => self::MESSAGE_ID]);
  }

  private function createProcessor(CommandBusPort $commandBus): DeleteMessageProcessor
  {
    return new DeleteMessageProcessor($commandBus, $this->securityWithUser());
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }

  private function view(): MessageView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new MessageView(
      self::MESSAGE_ID,
      'conversation-1',
      'org-1',
      'author-1',
      'Hello team',
      [],
      null,
      null,
      null,
      $now,
      $now,
      null,
      null,
    );
  }
  // #endregion
}
