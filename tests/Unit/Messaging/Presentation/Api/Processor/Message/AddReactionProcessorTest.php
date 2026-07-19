<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Message;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingReactionRepositoryPort, MessagingSavedMessageRepositoryPort};
use Messaging\Application\UseCase\Command\Message\AddReaction\{AddReactionCommand, AddReactionResult};
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Presentation\Api\Dto\Input\AddReactionInput;
use Messaging\Presentation\Api\Dto\Output\MessageOutput;
use Messaging\Presentation\Api\Factory\{MessageAttachmentOutputFactory, MessageOutputFactory};
use Messaging\Presentation\Api\Processor\Message\AddReactionProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

/**
 * Test AddReactionProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AddReactionProcessor::class)]
final class AddReactionProcessorTest extends TestCase
{
  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  #[Test]
  public function testProcessDispatchesTheAddReactionCommandAndReturnsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (AddReactionCommand $command): bool => self::USER_ID === $command->userId
        && self::MESSAGE_ID === $command->messageId
        && "\u{1F44D}" === $command->emoji))
      ->willReturn(new AddReactionResult($this->view(), 'member-1'));

    $processor = new AddReactionProcessor($commandBus, $this->outputFactory(), $this->securityWithUser());

    $input = new AddReactionInput();
    $input->emoji = "\u{1F44D}";

    $output = $processor->process($input, new Post(), ['id' => self::MESSAGE_ID]);

    self::assertInstanceOf(MessageOutput::class, $output);
    self::assertSame(self::MESSAGE_ID, $output->id);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissing(): void
  {
    $processor = new AddReactionProcessor($this->createStub(CommandBusPort::class), $this->outputFactory(), $this->securityWithUser());

    $input = new AddReactionInput();
    $input->emoji = "\u{1F44D}";

    $this->expectException(BadRequestHttpException::class);

    $processor->process($input, new Post(), []);
  }

  #[Test]
  public function testProcessThrowsWhenBodyIsInvalid(): void
  {
    $processor = new AddReactionProcessor($this->createStub(CommandBusPort::class), $this->outputFactory(), $this->securityWithUser());

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Post(), ['id' => self::MESSAGE_ID]);
  }

  #[Test]
  public function testProcessMapsNotFoundException(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessagingNotFoundException::message(self::MESSAGE_ID));

    $processor = new AddReactionProcessor($commandBus, $this->outputFactory(), $this->securityWithUser());

    $input = new AddReactionInput();
    $input->emoji = "\u{1F44D}";

    $this->expectException(NotFoundHttpException::class);

    $processor->process($input, new Post(), ['id' => self::MESSAGE_ID]);
  }

  #[Test]
  public function testProcessMapsValidationException(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingValidationException('The reaction emoji must be a plausible emoji character.'));

    $processor = new AddReactionProcessor($commandBus, $this->outputFactory(), $this->securityWithUser());

    $input = new AddReactionInput();
    $input->emoji = 'not-an-emoji';

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process($input, new Post(), ['id' => self::MESSAGE_ID]);
  }

  private function outputFactory(): MessageOutputFactory
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findByMessageIds')->willReturn([]);

    $reactions = $this->createStub(MessagingReactionRepositoryPort::class);
    $reactions->method('findByMessageIds')->willReturn([]);

    $savedMessages = $this->createStub(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->method('findSavedMessageIds')->willReturn([]);

    return new MessageOutputFactory($attachments, new MessageAttachmentOutputFactory(), $reactions, $savedMessages);
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
    );
  }
}
