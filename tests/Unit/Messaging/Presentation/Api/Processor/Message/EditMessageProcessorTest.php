<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Message;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingMemberDirectoryPort, MessagingReactionRepositoryPort, MessagingSavedMessageRepositoryPort};
use Messaging\Application\UseCase\Command\Message\EditMessage\{EditMessageCommand, EditMessageResult};
use Messaging\Domain\Exception\MessagingAccessDeniedException;
use Messaging\Presentation\Api\Dto\Input\{EditMessageInput, MessageReferenceInput};
use Messaging\Presentation\Api\Dto\Output\MessageOutput;
use Messaging\Presentation\Api\Factory\{MessageAttachmentOutputFactory, MessageOutputFactory};
use Messaging\Presentation\Api\Processor\Message\EditMessageProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, UnprocessableEntityHttpException};

/**
 * Test EditMessageProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EditMessageProcessor::class)]
final class EditMessageProcessorTest extends TestCase
{
  private const string MESSAGE_ID = 'message-1';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441400';

  #[Test]
  public function testProcessSanitizesTheBodyAndDispatchesTheEditCommand(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (EditMessageCommand $command): bool => self::USER_ID === $command->userId
        && self::MESSAGE_ID === $command->messageId
        && 'Corrected body' === $command->body
        && null === $command->references))
      ->willReturn(new EditMessageResult($this->view('Corrected body'), 'member-1'));

    $processor = new EditMessageProcessor($commandBus, $this->outputFactory(), $this->securityWithUser(), $this->passthroughSanitizer());

    $input = new EditMessageInput();
    $input->body = 'Corrected body';

    $output = $processor->process($input, new Patch(), ['id' => self::MESSAGE_ID]);

    self::assertInstanceOf(MessageOutput::class, $output);
    self::assertSame('Corrected body', $output->body);
  }

  #[Test]
  public function testProcessMapsReferencesIntoTheCommand(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (EditMessageCommand $command): bool => [
        ['type' => 'equipment', 'id' => 'equipment-1', 'label' => 'Extincteur A', 'code' => 'EQ-1'],
      ] === $command->references))
      ->willReturn(new EditMessageResult($this->view('Corrected body'), 'member-1'));

    $processor = new EditMessageProcessor($commandBus, $this->outputFactory(), $this->securityWithUser(), $this->passthroughSanitizer());

    $input = new EditMessageInput();
    $input->body = 'Corrected body';
    $reference = new MessageReferenceInput();
    $reference->type = 'equipment';
    $reference->id = 'equipment-1';
    $reference->label = 'Extincteur A';
    $reference->code = 'EQ-1';
    $input->references = [$reference];

    $processor->process($input, new Patch(), ['id' => self::MESSAGE_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new EditMessageProcessor(
      $this->createStub(CommandBusPort::class),
      $this->outputFactory(),
      $security,
      $this->passthroughSanitizer(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new EditMessageInput(), new Patch(), ['id' => self::MESSAGE_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenTheIdIsMissing(): void
  {
    $processor = new EditMessageProcessor(
      $this->createStub(CommandBusPort::class),
      $this->outputFactory(),
      $this->securityWithUser(),
      $this->passthroughSanitizer(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new EditMessageInput(), new Patch(), []);
  }

  #[Test]
  public function testProcessRejectsAnUnexpectedBody(): void
  {
    $processor = new EditMessageProcessor(
      $this->createStub(CommandBusPort::class),
      $this->outputFactory(),
      $this->securityWithUser(),
      $this->passthroughSanitizer(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new stdClass(), new Patch(), ['id' => self::MESSAGE_ID]);
  }

  #[Test]
  public function testProcessRejectsABodyThatSanitizesToEmpty(): void
  {
    $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
    $sanitizer->method('sanitize')->willReturn('<p>   </p>');

    $processor = new EditMessageProcessor(
      $this->createStub(CommandBusPort::class),
      $this->outputFactory(),
      $this->securityWithUser(),
      $sanitizer,
    );

    $input = new EditMessageInput();
    $input->body = '<script>alert(1)</script>';

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process($input, new Patch(), ['id' => self::MESSAGE_ID]);
  }

  #[Test]
  public function testProcessMapsADomainFailureToItsHttpCounterpart(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingAccessDeniedException('Only the author can edit.'));

    $processor = new EditMessageProcessor($commandBus, $this->outputFactory(), $this->securityWithUser(), $this->passthroughSanitizer());

    $input = new EditMessageInput();
    $input->body = 'Corrected body';

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($input, new Patch(), ['id' => self::MESSAGE_ID]);
  }

  private function passthroughSanitizer(): HtmlSanitizerInterface
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

    return new MessageView(self::MESSAGE_ID, 'conversation-1', 'org-1', 'member-1', $body, [], null, null, null, $now, $now);
  }
}
