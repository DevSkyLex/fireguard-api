<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\ReadMarker;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\UseCase\Command\ReadMarker\MarkConversationRead\{
  MarkConversationReadCommand,
  MarkConversationReadResult
};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Presentation\Api\Dto\Input\MarkConversationReadInput;
use Messaging\Presentation\Api\Factory\ConversationOutputFactory;
use Messaging\Presentation\Api\Processor\ReadMarker\MarkConversationReadProcessor;
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
 * Test MarkConversationReadProcessor.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MarkConversationReadProcessor::class)]
final class MarkConversationReadProcessorTest extends TestCase
{
  // #region Constants
  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655441700';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessForwardsTheLastReadMessageAndResetsTheUnreadCount(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MarkConversationReadCommand $command): bool => self::USER_ID === $command->userId
        && self::CONVERSATION_ID === $command->conversationId
        && self::MESSAGE_ID === $command->lastReadMessageId))
      ->willReturn(new MarkConversationReadResult($this->view()));

    $input = new MarkConversationReadInput();
    $input->lastReadMessageId = self::MESSAGE_ID;

    $output = $this->createProcessor($commandBus)->process($input, new Post(), ['id' => self::CONVERSATION_ID]);

    self::assertSame(self::CONVERSATION_ID, $output->id);
    self::assertSame(0, $output->unreadCount);
  }

  #[Test]
  public function testProcessAcceptsAnAbsentBodyAndSendsANullMarker(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MarkConversationReadCommand $command): bool => null === $command->lastReadMessageId))
      ->willReturn(new MarkConversationReadResult($this->view()));

    $output = $this->createProcessor($commandBus)->process(null, new Post(), ['id' => self::CONVERSATION_ID]);

    self::assertSame(self::CONVERSATION_ID, $output->id);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))->process(null, new Post(), []);
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new MarkConversationReadProcessor(
      $this->createStub(CommandBusPort::class),
      new ConversationOutputFactory(),
      $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), ['id' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testProcessMapsNotFoundExceptionToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessagingNotFoundException::conversation(self::CONVERSATION_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process(null, new Post(), ['id' => self::CONVERSATION_ID]);
  }

  private function createProcessor(CommandBusPort $commandBus): MarkConversationReadProcessor
  {
    return new MarkConversationReadProcessor(
      $commandBus,
      new ConversationOutputFactory(),
      $this->securityWithUser(),
    );
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

  private function view(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(
      self::CONVERSATION_ID,
      'org-1',
      'facility',
      'facility-1',
      'subject',
      null,
      1,
      false,
      $now,
      $now,
    );
  }
  // #endregion
}
