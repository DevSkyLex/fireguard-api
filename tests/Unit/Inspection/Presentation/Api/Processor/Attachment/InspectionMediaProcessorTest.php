<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\{Delete, Post};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment\{AddInspectionAttachmentCommand, AddInspectionAttachmentResult};
use Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment\DeleteInspectionAttachmentCommand;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionAttachmentRecord, InspectionRecord, NonConformityRecord};
use Inspection\Presentation\Api\Processor\Attachment\InspectionMediaProcessor;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Shared\Presentation\Api\Http\RevisionGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function base64_decode;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(InspectionMediaProcessor::class)]
final class InspectionMediaProcessorTest extends TestCase
{
  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string NON_CONFORMITY_ID = '550e8400-e29b-41d4-a716-446655440004';

  private const string MINIMAL_GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7';

  #[Test]
  public function testUploadForInspectionDispatchesCommandWhenAuthorized(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'inspection-attachment-');
    self::assertIsString($path);
    file_put_contents($path, (string) base64_decode(self::MINIMAL_GIF_BASE64, true));

    try {
      $organization = new OrganizationRecord();
      $organization->id = self::ORGANIZATION_ID;
      $inspection = new InspectionRecord();
      $inspection->id = self::INSPECTION_ID;
      $inspection->organization = $organization;

      $attachment = new InspectionAttachmentRecord();
      $attachment->id = 'attachment-id';
      $attachment->inspection = $inspection;
      $attachment->fileName = 'photo.gif';
      $attachment->mimeType = 'image/gif';
      $attachment->size = 5;
      $attachment->uploadedAt = new DateTimeImmutable();

      $entityManager = $this->createStub(EntityManagerInterface::class);
      $entityManager->method('wrapInTransaction')->willReturnCallback(
        static fn (callable $callback): mixed => $callback(),
      );
      $entityManager->method('find')->willReturnCallback(
        static fn (string $class): InspectionRecord|InspectionAttachmentRecord|null => match ($class) {
          InspectionRecord::class => $inspection,
          InspectionAttachmentRecord::class => $attachment,
          default => null,
        },
      );

      $authorization = $this->createMock(OrganizationAuthorizationPort::class);
      $authorization->expects(self::once())
        ->method('hasPermission')
        ->with('user-id', self::ORGANIZATION_ID, 'organization.inspection.write')
        ->willReturn(true);

      $security = $this->createStub(Security::class);
      $security->method('getUser')->willReturn(
        new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
      );

      $requestStack = new RequestStack();
      $requestStack->push(Request::create(
        '/api/inspections/' . self::INSPECTION_ID . '/attachments',
        'POST',
        [],
        [],
        ['file' => new UploadedFile($path, 'photo.gif', 'image/gif', null, true)],
      ));

      $commandBus = $this->createMock(CommandBusPort::class);
      $commandBus->expects(self::once())->method('dispatch')->willReturn(new AddInspectionAttachmentResult(
        $attachment->id,
        self::INSPECTION_ID,
        $attachment->fileName,
        $attachment->mimeType,
        $attachment->size,
        null,
        null,
        $attachment->uploadedAt,
      ));

      $result = new InspectionMediaProcessor(
        $entityManager,
        $commandBus,
        $authorization,
        $security,
        $requestStack,
        new MultipartAttachmentGuard(),
        new RevisionGuard($requestStack),
      )->process(null, new Post(), ['inspectionId' => self::INSPECTION_ID]);

      self::assertSame($attachment->id, $result?->id);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testUploadForNonConformityDispatchesCommandWithNonConformityId(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'inspection-attachment-');
    self::assertIsString($path);
    file_put_contents($path, (string) base64_decode(self::MINIMAL_GIF_BASE64, true));

    try {
      $organization = new OrganizationRecord();
      $organization->id = self::ORGANIZATION_ID;
      $inspection = new InspectionRecord();
      $inspection->id = self::INSPECTION_ID;
      $inspection->organization = $organization;
      $nonConformity = new NonConformityRecord();
      $nonConformity->id = self::NON_CONFORMITY_ID;
      $nonConformity->inspection = $inspection;

      $attachment = new InspectionAttachmentRecord();
      $attachment->id = 'attachment-id';
      $attachment->inspection = $inspection;
      $attachment->nonConformity = $nonConformity;
      $attachment->fileName = 'photo.gif';
      $attachment->mimeType = 'image/gif';
      $attachment->size = 5;
      $attachment->uploadedAt = new DateTimeImmutable();

      $entityManager = $this->createStub(EntityManagerInterface::class);
      $entityManager->method('wrapInTransaction')->willReturnCallback(
        static fn (callable $callback): mixed => $callback(),
      );
      $entityManager->method('find')->willReturnCallback(
        static fn (string $class): InspectionAttachmentRecord|NonConformityRecord|null => match ($class) {
          NonConformityRecord::class => $nonConformity,
          InspectionAttachmentRecord::class => $attachment,
          default => null,
        },
      );

      $authorization = $this->createStub(OrganizationAuthorizationPort::class);
      $authorization->method('hasPermission')->willReturn(true);

      $security = $this->createStub(Security::class);
      $security->method('getUser')->willReturn(
        new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
      );

      $requestStack = new RequestStack();
      $requestStack->push(Request::create(
        '/api/non-conformities/' . self::NON_CONFORMITY_ID . '/attachments',
        'POST',
        [],
        [],
        ['file' => new UploadedFile($path, 'photo.gif', 'image/gif', null, true)],
      ));

      $commandBus = $this->createMock(CommandBusPort::class);
      $commandBus->expects(self::once())
        ->method('dispatch')
        ->with(self::callback(
          static fn (AddInspectionAttachmentCommand $command): bool => self::NON_CONFORMITY_ID === $command->nonConformityId
            && self::INSPECTION_ID === $command->inspectionId,
        ))
        ->willReturn(new AddInspectionAttachmentResult(
          $attachment->id,
          self::INSPECTION_ID,
          $attachment->fileName,
          $attachment->mimeType,
          $attachment->size,
          self::NON_CONFORMITY_ID,
          null,
          $attachment->uploadedAt,
        ));

      $result = new InspectionMediaProcessor(
        $entityManager,
        $commandBus,
        $authorization,
        $security,
        $requestStack,
        new MultipartAttachmentGuard(),
        new RevisionGuard($requestStack),
      )->process(null, new Post(), ['nonConformityId' => self::NON_CONFORMITY_ID]);

      self::assertSame($attachment->id, $result?->id);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testUploadRejectsWhenMissingPermission(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION_ID;
    $inspection->organization = $organization;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->willReturn($inspection);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/inspections/' . self::INSPECTION_ID . '/attachments', 'POST'));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(AccessDeniedHttpException::class);

    new InspectionMediaProcessor(
      $entityManager,
      $commandBus,
      $authorization,
      $security,
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Post(), ['inspectionId' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testDeleteDispatchesCommandWhenRevisionMatches(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION_ID;
    $inspection->organization = $organization;
    $attachment = new InspectionAttachmentRecord();
    $attachment->id = 'attachment-id';
    $attachment->inspection = $inspection;
    $attachment->revision = 1;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->willReturn($attachment);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $requestStack = new RequestStack();
    $request = Request::create('/api/inspection-attachments/' . $attachment->id, 'DELETE');
    $request->headers->set('If-Match', '"revision-1"');
    $requestStack->push($request);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $command): bool => $command instanceof DeleteInspectionAttachmentCommand
          && self::ORGANIZATION_ID === $command->organizationId
          && self::INSPECTION_ID === $command->inspectionId
          && 'attachment-id' === $command->attachmentId,
      ));

    $result = new InspectionMediaProcessor(
      $entityManager,
      $commandBus,
      $authorization,
      $security,
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), ['id' => $attachment->id]);

    self::assertNull($result);
  }
}
