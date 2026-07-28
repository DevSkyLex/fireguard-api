<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Message;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingMemberDirectoryPort, MessagingReactionRepositoryPort, MessagingSavedMessageRepositoryPort};
use Messaging\Application\UseCase\Command\Message\PostReply\{PostReplyCommand, PostReplyResult};
use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Presentation\Api\Dto\Input\PostReplyInput;
use Messaging\Presentation\Api\Dto\Output\MessageOutput;
use Messaging\Presentation\Api\Factory\{MessageAttachmentOutputFactory, MessageOutputFactory};
use Messaging\Presentation\Api\Processor\Message\PostReplyProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, UnprocessableEntityHttpException};

/**
 * Test PostReplyProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PostReplyProcessor::class)]
final class PostReplyProcessorTest extends TestCase
{
  private const string PARENT_MESSAGE_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  #[Test]
  public function testProcessSanitizesTheBodyAndDispatchesThePostReplyCommand(): void
  {
    $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
    $sanitizer->method('sanitize')->willReturnArgument(0);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (PostReplyCommand $command): bool => self::USER_ID === $command->userId
        && self::PARENT_MESSAGE_ID === $command->parentMessageId
        && 'A reply' === $command->body))
      ->willReturn(new PostReplyResult($this->view('A reply'), 'author-1'));

    $processor = new PostReplyProcessor($commandBus, $this->outputFactory(), $this->securityWithUser(), $sanitizer);

    $input = new PostReplyInput();
    $input->body = 'A reply';

    $output = $processor->process($input, new Post(), ['id' => self::PARENT_MESSAGE_ID]);

    self::assertInstanceOf(MessageOutput::class, $output);
    self::assertSame('A reply', $output->body);
  }

  #[Test]
  public function testProcessRejectsABodyThatSanitizesToEmpty(): void
  {
    $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
    $sanitizer->method('sanitize')->willReturn('<p>   </p>');

    $processor = new PostReplyProcessor(
      $this->createStub(CommandBusPort::class),
      $this->outputFactory(),
      $this->securityWithUser(),
      $sanitizer,
    );

    $input = new PostReplyInput();
    $input->body = '<script>alert(1)</script>';

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process($input, new Post(), ['id' => self::PARENT_MESSAGE_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissing(): void
  {
    $processor = new PostReplyProcessor(
      $this->createStub(CommandBusPort::class),
      $this->outputFactory(),
      $this->securityWithUser(),
      $this->createStub(HtmlSanitizerInterface::class),
    );

    $input = new PostReplyInput();
    $input->body = 'A reply';

    $this->expectException(BadRequestHttpException::class);

    $processor->process($input, new Post(), []);
  }

  #[Test]
  public function testProcessMapsValidationExceptionToUnprocessableEntity(): void
  {
    $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
    $sanitizer->method('sanitize')->willReturnArgument(0);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingValidationException('Cannot reply to a deleted message.'));

    $processor = new PostReplyProcessor($commandBus, $this->outputFactory(), $this->securityWithUser(), $sanitizer);

    $input = new PostReplyInput();
    $input->body = 'A reply';

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process($input, new Post(), ['id' => self::PARENT_MESSAGE_ID]);
  }

  #[Test]
  public function testProcessRejectsAnUnexpectedBody(): void
  {
    $processor = new PostReplyProcessor(
      $this->createStub(CommandBusPort::class),
      $this->outputFactory(),
      $this->securityWithUser(),
      $this->createStub(HtmlSanitizerInterface::class),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new stdClass(), new Post(), ['id' => self::PARENT_MESSAGE_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new PostReplyProcessor($this->createStub(CommandBusPort::class), $this->outputFactory(), $security, $this->createStub(HtmlSanitizerInterface::class));

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), []);
  }

  private function outputFactory(): MessageOutputFactory
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findByMessageIds')->willReturn([]);

    $reactions = $this->createStub(MessagingReactionRepositoryPort::class);
    $reactions->method('findByMessageIds')->willReturn([]);

    $savedMessages = $this->createStub(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->method('findSavedMessageIds')->willReturn([]);

    return new MessageOutputFactory($attachments, new MessageAttachmentOutputFactory(), $reactions, $savedMessages, $this->createStub(MessagingMemberDirectoryPort::class));
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

    return new MessageView('reply-1', 'conversation-1', 'org-1', 'author-1', $body, [], null, null, null, $now, $now, null, null, self::PARENT_MESSAGE_ID, 0);
  }
}
