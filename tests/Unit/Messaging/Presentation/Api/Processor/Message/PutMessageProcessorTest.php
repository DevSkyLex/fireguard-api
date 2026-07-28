<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Message;

use ApiPlatform\Metadata\Put;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\{
  MessagingAttachmentRepositoryPort,
  MessagingMemberDirectoryPort,
  MessagingReactionRepositoryPort,
  MessagingSavedMessageRepositoryPort
};
use Messaging\Application\UseCase\Command\Message\PostMessage\{PostMessageCommand, PostMessageResult};
use Messaging\Domain\Exception\{
  MessagingClientMessageAlreadyExistsException,
  MessagingNotFoundException
};
use Messaging\Presentation\Api\Dto\Input\{MessageReferenceInput, PostMessageInput};
use Messaging\Presentation\Api\Factory\{MessageAttachmentOutputFactory, MessageOutputFactory};
use Messaging\Presentation\Api\Processor\Message\PutMessageProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\{
  ClientResourceAlreadyExistsHttpException,
  CreationPreconditionGuard
};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException,
  UnprocessableEntityHttpException
};

/**
 * Test PutMessageProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PutMessageProcessor::class)]
final class PutMessageProcessorTest extends TestCase
{
  // #region Constants
  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655441700';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655441200';

  private const string CLIENT_ID = 'client-message-1';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function incompleteUriVariablesProvider(): iterable
  {
    yield 'no variables' => [[]];
    yield 'blank conversationId' => [['conversationId' => '', 'clientId' => self::CLIENT_ID]];
    yield 'missing clientId' => [['conversationId' => self::CONVERSATION_ID]];
    yield 'blank clientId' => [['conversationId' => self::CONVERSATION_ID, 'clientId' => '']];
  }

  #[Test]
  public function testProcessPostsTheMessageUnderItsClientId(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (PostMessageCommand $command): bool => self::USER_ID === $command->userId
        && self::CONVERSATION_ID === $command->conversationId
        && '<p>Hello team</p>' === $command->body
        && self::CLIENT_ID === $command->clientId
        && [['type' => 'equipment', 'id' => 'equip-1', 'label' => 'Extinguisher', 'code' => 'EXT-1']] === $command->references))
      ->willReturn(new PostMessageResult($this->view(), self::MEMBER_ID));

    $reference = new MessageReferenceInput();
    $reference->type = 'equipment';
    $reference->id = 'equip-1';
    $reference->label = 'Extinguisher';
    $reference->code = 'EXT-1';

    $input = new PostMessageInput();
    $input->body = '<p>Hello team</p>';
    $input->references = [$reference];

    $output = $this->createProcessor($commandBus)->process($input, new Put(), $this->uriVariables());

    self::assertSame(self::MESSAGE_ID, $output->id);
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('incompleteUriVariablesProvider')]
  public function testProcessThrowsWhenUriVariablesAreIncomplete(array $uriVariables): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor()->process(new PostMessageInput(), new Put(), $uriVariables);
  }

  #[Test]
  public function testProcessThrowsWhenBodyIsInvalid(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor()->process(null, new Put(), $this->uriVariables());
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new PutMessageProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      mapper: $this->outputFactory(),
      security: $security,
      messageSanitizer: $this->sanitizer(),
      creationPreconditionGuard: new CreationPreconditionGuard($this->requestStack()),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new PostMessageInput(), new Put(), $this->uriVariables());
  }

  #[Test]
  public function testProcessRejectsABodyThatSanitisesToNothing(): void
  {
    $input = new PostMessageInput();
    $input->body = '<script>alert(1)</script>';

    $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
    $sanitizer->method('sanitize')->willReturn('   ');

    $processor = $this->createProcessor(sanitizer: $sanitizer);

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('The message body cannot be empty.');

    $processor->process($input, new Put(), $this->uriVariables());
  }

  #[Test]
  public function testProcessMapsAReplayedClientIdToHttp409(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessagingClientMessageAlreadyExistsException::forClientId(self::CLIENT_ID),
    );

    $input = new PostMessageInput();
    $input->body = 'Hello team';

    $this->expectException(ClientResourceAlreadyExistsHttpException::class);

    $this->createProcessor($commandBus)->process($input, new Put(), $this->uriVariables());
  }

  #[Test]
  public function testProcessStillMapsOrdinaryMessagingFailures(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessagingNotFoundException::conversation(self::CONVERSATION_ID),
    );

    $input = new PostMessageInput();
    $input->body = 'Hello team';

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process($input, new Put(), $this->uriVariables());
  }

  /**
   * @return array<string, string>
   */
  private function uriVariables(): array
  {
    return ['conversationId' => self::CONVERSATION_ID, 'clientId' => self::CLIENT_ID];
  }

  private function createProcessor(
    ?CommandBusPort $commandBus = null,
    ?HtmlSanitizerInterface $sanitizer = null,
  ): PutMessageProcessor {
    return new PutMessageProcessor(
      commandBus: $commandBus ?? $this->createStub(CommandBusPort::class),
      mapper: $this->outputFactory(),
      security: $this->securityWithUser(),
      messageSanitizer: $sanitizer ?? $this->sanitizer(),
      creationPreconditionGuard: new CreationPreconditionGuard($this->requestStack()),
    );
  }

  private function requestStack(): RequestStack
  {
    $stack = new RequestStack();
    $stack->push(Request::create(
      '/api/conversations/' . self::CONVERSATION_ID . '/messages/' . self::CLIENT_ID,
      'PUT',
      server: ['HTTP_IF_NONE_MATCH' => '*'],
    ));

    return $stack;
  }

  private function sanitizer(): HtmlSanitizerInterface
  {
    $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
    $sanitizer->method('sanitize')->willReturnArgument(0);

    return $sanitizer;
  }

  private function outputFactory(): MessageOutputFactory
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findByMessageIds')->willReturn([]);

    $reactions = $this->createStub(MessagingReactionRepositoryPort::class);
    $reactions->method('findByMessageIds')->willReturn([]);

    $savedMessages = $this->createStub(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->method('findSavedMessageIds')->willReturn([]);

    return new MessageOutputFactory(
      $attachments,
      new MessageAttachmentOutputFactory(),
      $reactions,
      $savedMessages,
      $this->createStub(MessagingMemberDirectoryPort::class),
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

  private function view(): MessageView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new MessageView(
      self::MESSAGE_ID,
      self::CONVERSATION_ID,
      'org-1',
      self::MEMBER_ID,
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
