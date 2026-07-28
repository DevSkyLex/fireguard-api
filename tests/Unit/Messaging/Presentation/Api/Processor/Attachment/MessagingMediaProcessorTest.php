<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\{Delete, Post};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment\{AddMessageAttachmentCommand, AddMessageAttachmentResult};
use Messaging\Application\UseCase\Command\Attachment\DeleteMessageAttachment\DeleteMessageAttachmentCommand;
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingAttachmentRecord, MessagingMessageRecord};
use Messaging\Presentation\Api\Dto\Output\MessageAttachmentOutput;
use Messaging\Presentation\Api\Processor\Attachment\MessagingMediaProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Shared\Presentation\Api\Http\RevisionGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, PreconditionFailedHttpException};

use function base64_decode;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Test MessagingMediaProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingMediaProcessor::class)]
final class MessagingMediaProcessorTest extends TestCase
{
  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ATTACHMENT_ID = 'attachment-id';

  private const string MINIMAL_GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7';

  #[Test]
  public function testUploadDispatchesTheCommandAndReturnsTheOutput(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'messaging-attachment-');
    self::assertIsString($path);
    file_put_contents($path, (string) base64_decode(self::MINIMAL_GIF_BASE64, true));

    try {
      $message = new MessagingMessageRecord();
      $message->id = self::MESSAGE_ID;

      $attachment = new MessagingAttachmentRecord();
      $attachment->id = self::ATTACHMENT_ID;
      $attachment->message = $message;
      $attachment->conversationId = 'conversation-1';
      $attachment->organizationId = 'org-1';
      $attachment->uploadedByMemberId = 'member-1';
      $attachment->fileName = 'photo.gif';
      $attachment->mimeType = 'image/gif';
      $attachment->size = 5;
      $attachment->uploadedAt = new DateTimeImmutable();

      $entityManager = $this->createStub(EntityManagerInterface::class);
      $entityManager->method('find')->willReturnCallback(
        static fn (string $class): ?MessagingAttachmentRecord => MessagingAttachmentRecord::class === $class ? $attachment : null,
      );

      $security = $this->createStub(Security::class);
      $security->method('getUser')->willReturn(
        new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
      );

      $requestStack = new RequestStack();
      $requestStack->push(Request::create(
        '/api/messages/' . self::MESSAGE_ID . '/attachments',
        'POST',
        [],
        [],
        ['file' => new UploadedFile($path, 'photo.gif', 'image/gif', null, true)],
      ));

      $commandBus = $this->createMock(CommandBusPort::class);
      $commandBus->expects(self::once())
        ->method('dispatch')
        ->with(self::callback(
          static fn (AddMessageAttachmentCommand $command): bool => self::MESSAGE_ID === $command->messageId
            && 'user-id' === $command->userId
            && 'photo.gif' === $command->fileName,
        ))
        ->willReturn(new AddMessageAttachmentResult(
          $attachment->id,
          self::MESSAGE_ID,
          'conversation-1',
          'org-1',
          'member-1',
          $attachment->fileName,
          $attachment->mimeType,
          $attachment->size,
          null,
          $attachment->uploadedAt,
        ));

      $result = new MessagingMediaProcessor(
        $entityManager,
        $commandBus,
        $security,
        $requestStack,
        new MultipartAttachmentGuard(),
        new RevisionGuard($requestStack),
      )->process(null, new Post(), ['messageId' => self::MESSAGE_ID]);

      self::assertInstanceOf(MessageAttachmentOutput::class, $result);
      self::assertSame($attachment->id, $result->id);
      self::assertSame('/api/messages/' . self::MESSAGE_ID, $result->message);
      self::assertSame('/api/conversations/conversation-1', $result->conversation);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testUploadThrowsWhenMessageIdIsMissing(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $security = $this->createStub(Security::class);
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/messages//attachments', 'POST'));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(BadRequestHttpException::class);

    new MessagingMediaProcessor(
      $entityManager,
      $commandBus,
      $security,
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Post(), []);
  }

  #[Test]
  public function testDeleteDispatchesTheCommandWhenRevisionMatches(): void
  {
    $attachment = new MessagingAttachmentRecord();
    $attachment->id = self::ATTACHMENT_ID;
    $attachment->revision = 1;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($attachment);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $requestStack = new RequestStack();
    $request = Request::create('/api/messaging-attachments/' . $attachment->id, 'DELETE');
    $request->headers->set('If-Match', '"revision-1"');
    $requestStack->push($request);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $command): bool => $command instanceof DeleteMessageAttachmentCommand
          && 'user-id' === $command->userId
          && self::ATTACHMENT_ID === $command->attachmentId,
      ));

    $result = new MessagingMediaProcessor(
      $entityManager,
      $commandBus,
      $security,
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), ['id' => $attachment->id]);

    self::assertNull($result);
  }

  #[Test]
  public function testDeleteThrowsWhenRevisionIsStale(): void
  {
    $attachment = new MessagingAttachmentRecord();
    $attachment->id = self::ATTACHMENT_ID;
    $attachment->revision = 2;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($attachment);

    $security = $this->createStub(Security::class);

    $requestStack = new RequestStack();
    $request = Request::create('/api/messaging-attachments/' . $attachment->id, 'DELETE');
    $request->headers->set('If-Match', '"revision-1"');
    $requestStack->push($request);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(PreconditionFailedHttpException::class);

    new MessagingMediaProcessor(
      $entityManager,
      $commandBus,
      $security,
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), ['id' => $attachment->id]);
  }

  #[Test]
  public function testUploadMapsMessagingExceptionsRaisedByTheCommandBus(): void
  {
    $path = $this->uploadedFilePath();

    try {
      $entityManager = $this->createStub(EntityManagerInterface::class);

      $requestStack = new RequestStack();
      $requestStack->push($this->uploadRequest($path));

      $commandBus = $this->createStub(CommandBusPort::class);
      $commandBus->method('dispatch')->willThrowException(MessagingNotFoundException::message(self::MESSAGE_ID));

      $this->expectException(NotFoundHttpException::class);

      new MessagingMediaProcessor(
        $entityManager,
        $commandBus,
        $this->securityWithUser(),
        $requestStack,
        new MultipartAttachmentGuard(),
        new RevisionGuard($requestStack),
      )->process(null, new Post(), ['messageId' => self::MESSAGE_ID]);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testUploadThrowsNotFoundWhenTheStoredAttachmentCannotBeReadBack(): void
  {
    $path = $this->uploadedFilePath();

    try {
      $entityManager = $this->createStub(EntityManagerInterface::class);
      $entityManager->method('find')->willReturn(null);

      $requestStack = new RequestStack();
      $requestStack->push($this->uploadRequest($path));

      $commandBus = $this->createStub(CommandBusPort::class);
      $commandBus->method('dispatch')->willReturn(new AddMessageAttachmentResult(
        self::ATTACHMENT_ID,
        self::MESSAGE_ID,
        'conversation-1',
        'org-1',
        'member-1',
        'photo.gif',
        'image/gif',
        5,
        null,
        new DateTimeImmutable(),
      ));

      $this->expectException(NotFoundHttpException::class);

      new MessagingMediaProcessor(
        $entityManager,
        $commandBus,
        $this->securityWithUser(),
        $requestStack,
        new MultipartAttachmentGuard(),
        new RevisionGuard($requestStack),
      )->process(null, new Post(), ['messageId' => self::MESSAGE_ID]);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testUploadThrowsWhenTheUserIsNotAuthenticated(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/messages/' . self::MESSAGE_ID . '/attachments', 'POST'));

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(AccessDeniedHttpException::class);

    new MessagingMediaProcessor(
      $this->createStub(EntityManagerInterface::class),
      $commandBus,
      $security,
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Post(), ['messageId' => self::MESSAGE_ID]);
  }

  #[Test]
  public function testUploadThrowsWhenThereIsNoCurrentRequest(): void
  {
    $requestStack = new RequestStack();

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(BadRequestHttpException::class);

    new MessagingMediaProcessor(
      $this->createStub(EntityManagerInterface::class),
      $commandBus,
      $this->securityWithUser(),
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Post(), ['messageId' => self::MESSAGE_ID]);
  }

  #[Test]
  public function testDeleteThrowsNotFoundWhenTheIdUriVariableIsMissing(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/messaging-attachments/', 'DELETE'));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(NotFoundHttpException::class);

    new MessagingMediaProcessor(
      $this->createStub(EntityManagerInterface::class),
      $commandBus,
      $this->createStub(Security::class),
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), []);
  }

  #[Test]
  public function testDeleteMapsMessagingExceptionsRaisedByTheCommandBus(): void
  {
    $attachment = new MessagingAttachmentRecord();
    $attachment->id = self::ATTACHMENT_ID;
    $attachment->revision = 1;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($attachment);

    $requestStack = new RequestStack();
    $request = Request::create('/api/messaging-attachments/' . $attachment->id, 'DELETE');
    $request->headers->set('If-Match', '"revision-1"');
    $requestStack->push($request);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingAccessDeniedException('Not the uploader.'));

    $this->expectException(AccessDeniedHttpException::class);

    new MessagingMediaProcessor(
      $entityManager,
      $commandBus,
      $this->securityWithUser(),
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), ['id' => $attachment->id]);
  }

  #[Test]
  public function testDeleteThrowsNotFoundWhenAttachmentIsMissing(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);

    $security = $this->createStub(Security::class);

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/messaging-attachments/missing', 'DELETE'));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(NotFoundHttpException::class);

    new MessagingMediaProcessor(
      $entityManager,
      $commandBus,
      $security,
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), ['id' => 'missing']);
  }

  private function uploadedFilePath(): string
  {
    $path = tempnam(sys_get_temp_dir(), 'messaging-attachment-');
    self::assertIsString($path);
    file_put_contents($path, (string) base64_decode(self::MINIMAL_GIF_BASE64, true));

    return $path;
  }

  private function uploadRequest(string $path): Request
  {
    return Request::create(
      '/api/messages/' . self::MESSAGE_ID . '/attachments',
      'POST',
      [],
      [],
      ['file' => new UploadedFile($path, 'photo.gif', 'image/gif', null, true)],
    );
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return $security;
  }
}
