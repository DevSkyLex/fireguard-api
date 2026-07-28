<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\{Delete, Post};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment\{AddInterventionAttachmentCommand, AddInterventionAttachmentResult};
use Intervention\Application\UseCase\Command\Attachment\DeleteInterventionAttachment\DeleteInterventionAttachmentCommand;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionAttachmentNotFoundException, InterventionConflictException};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionAttachmentRecord, InterventionRecord};
use Intervention\Presentation\Api\Processor\Attachment\InterventionMediaProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Shared\Presentation\Api\Http\RevisionGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException, PreconditionRequiredHttpException};

use function base64_decode;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Test InterventionMediaProcessorTest.
 *
 * The processor makes NO authorization decision of its own — the
 * phase-based write permission and the flat read permission are enforced
 * authoritatively inside the command/query handlers (see
 * `Add`/`DeleteInterventionAttachmentHandlerTest` and
 * `ListInterventionAttachmentsHandlerTest`, plus
 * `tests/Architecture/Unit/InterventionAuthorizationEnforcementTest`). This
 * test only covers request wiring, `If-Match` revision handling, and
 * exception -> HTTP status mapping.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionMediaProcessor::class)]
final class InterventionMediaProcessorTest extends TestCase
{
  private const string USER_ID = 'user-id';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string MINIMAL_GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7';

  #[Test]
  public function testUploadDispatchesCommandWithTheAuthenticatedUserId(): void
  {
    $path = $this->tempImage();

    try {
      $attachment = $this->attachmentRecord();

      $entityManager = $this->wrappingEntityManager();
      $entityManager->method('find')->willReturn($attachment);

      $commandBus = $this->createMock(CommandBusPort::class);
      $commandBus->expects(self::once())
        ->method('dispatch')
        ->with(self::callback(
          static fn (AddInterventionAttachmentCommand $command): bool => self::USER_ID === $command->userId
            && self::INTERVENTION_ID === $command->interventionId,
        ))
        ->willReturn(new AddInterventionAttachmentResult(
          $attachment->id,
          self::INTERVENTION_ID,
          $attachment->fileName,
          $attachment->mimeType,
          $attachment->size,
          null,
          $attachment->uploadedAt,
        ));

      $result = new InterventionMediaProcessor(
        $entityManager,
        $commandBus,
        $this->userSecurity(),
        $this->requestStackWithUpload($path),
        new MultipartAttachmentGuard(),
        new RevisionGuard(new RequestStack()),
      )->process(null, new Post(), ['interventionId' => self::INTERVENTION_ID]);

      self::assertSame($attachment->id, $result?->id);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testUploadMapsAccessDeniedExceptionTo403(): void
  {
    $path = $this->tempImage();

    try {
      $commandBus = $this->createStub(CommandBusPort::class);
      $commandBus->method('dispatch')->willThrowException(
        new InterventionAccessDeniedException('Missing organization.interventions.execute permission.'),
      );

      $this->expectException(AccessDeniedHttpException::class);

      new InterventionMediaProcessor(
        $this->wrappingEntityManager(),
        $commandBus,
        $this->userSecurity(),
        $this->requestStackWithUpload($path),
        new MultipartAttachmentGuard(),
        new RevisionGuard(new RequestStack()),
      )->process(null, new Post(), ['interventionId' => self::INTERVENTION_ID]);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testUploadMapsConflictExceptionTo409(): void
  {
    $path = $this->tempImage();

    try {
      $commandBus = $this->createStub(CommandBusPort::class);
      $commandBus->method('dispatch')->willThrowException(
        new InterventionConflictException('Intervention resources are immutable in the current state.'),
      );

      $this->expectException(ConflictHttpException::class);

      new InterventionMediaProcessor(
        $this->wrappingEntityManager(),
        $commandBus,
        $this->userSecurity(),
        $this->requestStackWithUpload($path),
        new MultipartAttachmentGuard(),
        new RevisionGuard(new RequestStack()),
      )->process(null, new Post(), ['interventionId' => self::INTERVENTION_ID]);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testDeleteRequiresIfMatchHeader(): void
  {
    $attachment = $this->attachmentRecord();

    $entityManager = $this->wrappingEntityManager();
    $entityManager->method('find')->willReturn($attachment);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/intervention-attachments/' . $attachment->id, 'DELETE'));

    $this->expectException(PreconditionRequiredHttpException::class);

    new InterventionMediaProcessor(
      $entityManager,
      $commandBus,
      $this->userSecurity(),
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), ['id' => $attachment->id]);
  }

  #[Test]
  public function testDeleteDispatchesCommandWhenRevisionMatches(): void
  {
    $attachment = $this->attachmentRecord();
    $attachment->revision = 1;

    $entityManager = $this->wrappingEntityManager();
    $entityManager->method('find')->willReturn($attachment);

    $requestStack = new RequestStack();
    $request = Request::create('/api/intervention-attachments/' . $attachment->id, 'DELETE');
    $request->headers->set('If-Match', '"revision-1"');
    $requestStack->push($request);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $command): bool => $command instanceof DeleteInterventionAttachmentCommand
          && self::USER_ID === $command->userId
          && self::INTERVENTION_ID === $command->interventionId
          && 'attachment-id' === $command->attachmentId,
      ));

    $result = new InterventionMediaProcessor(
      $entityManager,
      $commandBus,
      $this->userSecurity(),
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), ['id' => $attachment->id]);

    self::assertNull($result);
  }

  #[Test]
  public function testDeleteMapsAttachmentNotFoundExceptionTo404(): void
  {
    $attachment = $this->attachmentRecord();
    $attachment->revision = 1;

    $entityManager = $this->wrappingEntityManager();
    $entityManager->method('find')->willReturn($attachment);

    $requestStack = new RequestStack();
    $request = Request::create('/api/intervention-attachments/' . $attachment->id, 'DELETE');
    $request->headers->set('If-Match', '"revision-1"');
    $requestStack->push($request);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      InterventionAttachmentNotFoundException::withId($attachment->id),
    );

    $this->expectException(NotFoundHttpException::class);

    new InterventionMediaProcessor(
      $entityManager,
      $commandBus,
      $this->userSecurity(),
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), ['id' => $attachment->id]);
  }

  #[Test]
  public function testUploadRejectsAMissingInterventionIdUriVariable(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(BadRequestHttpException::class);

    new InterventionMediaProcessor(
      $this->wrappingEntityManager(),
      $commandBus,
      $this->userSecurity(),
      new RequestStack(),
      new MultipartAttachmentGuard(),
      new RevisionGuard(new RequestStack()),
    )->process(null, new Post(), []);
  }

  #[Test]
  public function testUploadRejectsAnUnauthenticatedUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(AccessDeniedHttpException::class);

    new InterventionMediaProcessor(
      $this->wrappingEntityManager(),
      $commandBus,
      $security,
      new RequestStack(),
      new MultipartAttachmentGuard(),
      new RevisionGuard(new RequestStack()),
    )->process(null, new Post(), ['interventionId' => self::INTERVENTION_ID]);
  }

  #[Test]
  public function testUploadRejectsAMissingRequest(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(BadRequestHttpException::class);

    new InterventionMediaProcessor(
      $this->wrappingEntityManager(),
      $commandBus,
      $this->userSecurity(),
      new RequestStack(),
      new MultipartAttachmentGuard(),
      new RevisionGuard(new RequestStack()),
    )->process(null, new Post(), ['interventionId' => self::INTERVENTION_ID]);
  }

  #[Test]
  public function testUploadThrowsWhenTheStoredAttachmentCannotBeReloaded(): void
  {
    $path = $this->tempImage();

    try {
      $entityManager = $this->wrappingEntityManager();
      $entityManager->method('find')->willReturn(null);

      $commandBus = $this->createStub(CommandBusPort::class);
      $commandBus->method('dispatch')->willReturn(new AddInterventionAttachmentResult(
        'attachment-id',
        self::INTERVENTION_ID,
        'photo.gif',
        'image/gif',
        5,
        null,
        new DateTimeImmutable(),
      ));

      $this->expectException(NotFoundHttpException::class);
      $this->expectExceptionMessage('Uploaded attachment not found.');

      new InterventionMediaProcessor(
        $entityManager,
        $commandBus,
        $this->userSecurity(),
        $this->requestStackWithUpload($path),
        new MultipartAttachmentGuard(),
        new RevisionGuard(new RequestStack()),
      )->process(null, new Post(), ['interventionId' => self::INTERVENTION_ID]);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testDeleteRejectsANonStringAttachmentId(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/intervention-attachments/x', 'DELETE'));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(NotFoundHttpException::class);

    new InterventionMediaProcessor(
      $this->wrappingEntityManager(),
      $commandBus,
      $this->userSecurity(),
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), []);
  }

  #[Test]
  public function testDeleteThrowsWhenTheAttachmentRecordIsMissing(): void
  {
    $entityManager = $this->wrappingEntityManager();
    $entityManager->method('find')->willReturn(null);

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/intervention-attachments/attachment-id', 'DELETE'));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(NotFoundHttpException::class);

    new InterventionMediaProcessor(
      $entityManager,
      $commandBus,
      $this->userSecurity(),
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function testDeleteMapsANonNotFoundWorkflowExceptionToItsHttpStatus(): void
  {
    $attachment = $this->attachmentRecord();
    $attachment->revision = 1;

    $entityManager = $this->wrappingEntityManager();
    $entityManager->method('find')->willReturn($attachment);

    $requestStack = new RequestStack();
    $request = Request::create('/api/intervention-attachments/' . $attachment->id, 'DELETE');
    $request->headers->set('If-Match', '"revision-1"');
    $requestStack->push($request);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      new InterventionAccessDeniedException('Missing organization.interventions.execute permission.'),
    );

    $this->expectException(AccessDeniedHttpException::class);

    new InterventionMediaProcessor(
      $entityManager,
      $commandBus,
      $this->userSecurity(),
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), ['id' => $attachment->id]);
  }

  private function tempImage(): string
  {
    $path = tempnam(sys_get_temp_dir(), 'intervention-attachment-');
    self::assertIsString($path);
    file_put_contents($path, (string) base64_decode(self::MINIMAL_GIF_BASE64, true));

    return $path;
  }

  private function attachmentRecord(): InterventionAttachmentRecord
  {
    $intervention = new InterventionRecord();
    $intervention->id = self::INTERVENTION_ID;

    $attachment = new InterventionAttachmentRecord();
    $attachment->id = 'attachment-id';
    $attachment->intervention = $intervention;
    $attachment->fileName = 'photo.gif';
    $attachment->mimeType = 'image/gif';
    $attachment->size = 5;
    $attachment->uploadedAt = new DateTimeImmutable();

    return $attachment;
  }

  /**
   * @return EntityManagerInterface&Stub
   */
  private function wrappingEntityManager(): EntityManagerInterface
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );

    return $entityManager;
  }

  private function requestStackWithUpload(string $path): RequestStack
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      '/api/interventions/' . self::INTERVENTION_ID . '/attachments',
      'POST',
      [],
      [],
      ['file' => new UploadedFile($path, 'photo.gif', 'image/gif', null, true)],
    ));

    return $requestStack;
  }

  private function userSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return $security;
  }
}
