<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\{Delete, Post};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment\{AddMessageAttachmentCommand, AddMessageAttachmentResult};
use Messaging\Application\UseCase\Command\Attachment\DeleteMessageAttachment\DeleteMessageAttachmentCommand;
use Messaging\Domain\Exception\{MessagingAttachmentNotFoundException, MessagingRevisionPreconditionException};
use Messaging\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Messaging\Presentation\Api\Dto\Output\MessageAttachmentOutput;
use Messaging\Presentation\Api\Factory\MessageAttachmentOutputFactory;
use Messaging\Presentation\Api\Processor\Attachment\MessagingMediaProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, PreconditionFailedHttpException, PreconditionRequiredHttpException};

use function base64_decode;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Class MessagingMediaProcessorTest
 *
 * Verifies HTTP translation without persistence or a post-commit metadata query.
 *
 * @category Processor Tests
 */
#[CoversClass(MessagingMediaProcessor::class)]
final class MessagingMediaProcessorTest extends TestCase
{
  #[Test]
  public function uploadReturnsTheCommittedResultWithoutReloadingPersistence(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'messaging-upload-');
    self::assertIsString($path);
    file_put_contents($path, (string) base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7', true));

    try {
      $request = Request::create('/api/messages/message-id/attachments', 'POST', files: [
        'file' => new UploadedFile($path, 'photo.gif', 'image/gif', null, true),
      ]);
      $bus = $this->createMock(CommandBusPort::class);
      $bus->expects(self::once())->method('dispatch')
        ->with(self::callback(static fn (AddMessageAttachmentCommand $command): bool => 'message-id' === $command->messageId && 'user-id' === $command->userId && 'photo.gif' === $command->fileName))
        ->willReturn(new AddMessageAttachmentResult('attachment-id', 'message-id', 'conversation-id', 'org-id', 'member-id', 'photo.gif', 'image/gif', 34, null, new DateTimeImmutable('2026-01-01'), 3));
      $output = $this->processor($bus, $request)->process(null, new Post(), ['messageId' => 'message-id']);

      self::assertInstanceOf(MessageAttachmentOutput::class, $output);
      self::assertSame('attachment-id', $output->id);
      self::assertSame('/api/messages/message-id', $output->message);
      self::assertSame('/api/messaging-attachments/attachment-id/content', $output->contentUrl);
      self::assertSame(3, $output->revision);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function deletePassesTheRawPreconditionToTheOwningCommand(): void
  {
    $request = Request::create('/api/messaging-attachments/attachment-id', 'DELETE');
    $request->headers->set('If-Match', '"revision-3"');
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (DeleteMessageAttachmentCommand $command): bool => 'attachment-id' === $command->attachmentId && 'user-id' === $command->userId && '"revision-3"' === $command->ifMatch,
    ));

    self::assertNull($this->processor($bus, $request)->process(null, new Delete(), ['id' => 'attachment-id']));
  }

  #[Test]
  public function deleteMapsMissingAndStalePreconditions(): void
  {
    foreach ([true => PreconditionRequiredHttpException::class, false => PreconditionFailedHttpException::class] as $missing => $httpException) {
      $bus = $this->createStub(CommandBusPort::class);
      $bus->method('dispatch')->willThrowException(new MessagingRevisionPreconditionException((bool) $missing));

      try {
        $this->processor($bus, Request::create('/api/messaging-attachments/id', 'DELETE'))->process(null, new Delete(), ['id' => 'attachment-id']);
        self::fail('The owning precondition failure must propagate.');
      } catch (PreconditionRequiredHttpException|PreconditionFailedHttpException $exception) {
        self::assertInstanceOf($httpException, $exception);
      }
    }
  }

  #[Test]
  public function missingAttachmentIsMappedBeforeItsMissingPrecondition(): void
  {
    $bus = $this->createStub(CommandBusPort::class);
    $bus->method('dispatch')->willThrowException(MessagingAttachmentNotFoundException::withId('missing'));
    $this->expectException(NotFoundHttpException::class);
    $this->processor($bus, Request::create('/api/messaging-attachments/missing', 'DELETE'))->process(null, new Delete(), ['id' => 'missing']);
  }

  #[Test]
  public function uploadRequiresTheMessageUriParameter(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $this->expectException(BadRequestHttpException::class);
    $this->processor($bus, Request::create('/', 'POST'))->process(null, new Post());
  }

  #[Test]
  public function deleteRequiresTheAttachmentUriParameter(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->processor($this->createStub(CommandBusPort::class), Request::create('/', 'DELETE'))->process(null, new Delete());
  }

  #[Test]
  public function unauthenticatedRequestsNeverDispatchAMutation(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $this->expectException(AccessDeniedHttpException::class);
    $this->processor($bus, Request::create('/', 'DELETE'), authenticated: false)->process(null, new Delete(), ['id' => 'attachment-id']);
  }

  private function processor(CommandBusPort $bus, Request $request, bool $authenticated = true): MessagingMediaProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($authenticated ? new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true) : null);
    $stack = new RequestStack();
    $stack->push($request);

    return new MessagingMediaProcessor(new MessageAttachmentOutputFactory(), $bus, $security, $stack, new MultipartAttachmentGuard());
  }
}
