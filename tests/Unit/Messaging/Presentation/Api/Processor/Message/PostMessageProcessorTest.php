<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Message;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingReactionRepositoryPort, MessagingSavedMessageRepositoryPort};
use Messaging\Application\UseCase\Command\Message\PostMessage\{PostMessageCommand, PostMessageResult};
use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Presentation\Api\Dto\Input\{MessageReferenceInput, PostMessageInput};
use Messaging\Presentation\Api\Dto\Output\MessageOutput;
use Messaging\Presentation\Api\Factory\{MessageAttachmentOutputFactory, MessageOutputFactory};
use Messaging\Presentation\Api\Processor\Message\PostMessageProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, UnprocessableEntityHttpException};

/**
 * Test PostMessageProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PostMessageProcessor::class)]
final class PostMessageProcessorTest extends TestCase
{
  private const string CONVERSATION_ID = 'conversation-1';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  #[Test]
  public function testProcessSanitizesTheBodyAndDispatchesThePostCommand(): void
  {
    $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
    $sanitizer->method('sanitize')->willReturnArgument(0);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (PostMessageCommand $command): bool => self::USER_ID === $command->userId
        && self::CONVERSATION_ID === $command->conversationId
        && 'Hello team' === $command->body))
      ->willReturn(new PostMessageResult($this->view('Hello team'), 'author-1'));

    $processor = new PostMessageProcessor($commandBus, $this->outputFactory(), $this->securityWithUser(), $sanitizer);

    $input = new PostMessageInput();
    $input->body = 'Hello team';

    $output = $processor->process($input, new Post(), ['conversationId' => self::CONVERSATION_ID]);

    self::assertInstanceOf(MessageOutput::class, $output);
    self::assertSame('Hello team', $output->body);
  }

  #[Test]
  public function testProcessMapsReferencesIntoTheCommand(): void
  {
    $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
    $sanitizer->method('sanitize')->willReturnArgument(0);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (PostMessageCommand $command): bool => [
        ['type' => 'facility', 'id' => 'facility-1', 'label' => 'Site Nord', 'code' => null],
      ] === $command->references))
      ->willReturn(new PostMessageResult($this->view('Hello team'), 'author-1'));

    $processor = new PostMessageProcessor($commandBus, $this->outputFactory(), $this->securityWithUser(), $sanitizer);

    $input = new PostMessageInput();
    $input->body = 'Hello team';
    $reference = new MessageReferenceInput();
    $reference->type = 'facility';
    $reference->id = 'facility-1';
    $reference->label = 'Site Nord';
    $input->references = [$reference];

    $processor->process($input, new Post(), ['conversationId' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testProcessRejectsABodyThatSanitizesToEmpty(): void
  {
    $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
    $sanitizer->method('sanitize')->willReturn('<p>   </p>');

    $processor = new PostMessageProcessor(
      $this->createStub(CommandBusPort::class),
      $this->outputFactory(),
      $this->securityWithUser(),
      $sanitizer,
    );

    $input = new PostMessageInput();
    $input->body = '<script>alert(1)</script>';

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process($input, new Post(), ['conversationId' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenConversationIdIsMissing(): void
  {
    $sanitizer = $this->createStub(HtmlSanitizerInterface::class);

    $processor = new PostMessageProcessor(
      $this->createStub(CommandBusPort::class),
      $this->outputFactory(),
      $this->securityWithUser(),
      $sanitizer,
    );

    $input = new PostMessageInput();
    $input->body = 'Hello';

    $this->expectException(BadRequestHttpException::class);

    $processor->process($input, new Post(), []);
  }

  #[Test]
  public function testProcessMapsValidationExceptionToUnprocessableEntity(): void
  {
    $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
    $sanitizer->method('sanitize')->willReturnArgument(0);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingValidationException('Cannot post to an archived conversation.'));

    $processor = new PostMessageProcessor($commandBus, $this->outputFactory(), $this->securityWithUser(), $sanitizer);

    $input = new PostMessageInput();
    $input->body = 'Hello';

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process($input, new Post(), ['conversationId' => self::CONVERSATION_ID]);
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

  private function view(string $body): MessageView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new MessageView('message-1', self::CONVERSATION_ID, 'org-1', 'author-1', $body, [], null, null, null, $now, $now);
  }
}
