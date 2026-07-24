<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\{Delete, Post};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment\AddFacilityAttachmentResult;
use Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment\DeleteFacilityAttachmentCommand;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use Facility\Presentation\Api\Processor\Attachment\FacilityMediaProcessor;
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
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, PreconditionRequiredHttpException};

use function base64_decode;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(FacilityMediaProcessor::class)]
final class FacilityMediaProcessorTest extends TestCase
{
  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440003';

  /**
   * A minimal valid 1x1 transparent GIF. MIME sniffing (fileinfo) reads the
   * real bytes, so the fixture must be a real image.
   */
  private const string MINIMAL_GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7';

  #[Test]
  public function testUploadDispatchesCommandWhenAuthorized(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'facility-attachment-');
    self::assertIsString($path);
    file_put_contents($path, (string) base64_decode(self::MINIMAL_GIF_BASE64, true));

    try {
      $organization = new OrganizationRecord();
      $organization->id = self::ORGANIZATION_ID;
      $facility = new FacilityRecord();
      $facility->id = self::FACILITY_ID;
      $facility->organization = $organization;

      $attachment = new FacilityAttachmentRecord();
      $attachment->id = 'attachment-id';
      $attachment->facility = $facility;
      $attachment->fileName = 'photo.gif';
      $attachment->mimeType = 'image/gif';
      $attachment->size = 5;
      $attachment->uploadedAt = new DateTimeImmutable();

      $entityManager = $this->createStub(EntityManagerInterface::class);
      $entityManager->method('wrapInTransaction')->willReturnCallback(
        static fn (callable $callback): mixed => $callback(),
      );
      $entityManager->method('find')->willReturnCallback(
        static fn (string $class): FacilityRecord|FacilityAttachmentRecord|null => match ($class) {
          FacilityRecord::class => $facility,
          FacilityAttachmentRecord::class => $attachment,
          default => null,
        },
      );

      $authorization = $this->createMock(OrganizationAuthorizationPort::class);
      $authorization->expects(self::once())
        ->method('hasPermission')
        ->with('user-id', self::ORGANIZATION_ID, 'organization.facilities.write')
        ->willReturn(true);

      $security = $this->createStub(Security::class);
      $security->method('getUser')->willReturn(
        new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
      );

      $requestStack = new RequestStack();
      $requestStack->push(Request::create(
        '/api/facilities/' . self::FACILITY_ID . '/attachments',
        'POST',
        [],
        [],
        ['file' => new UploadedFile($path, 'photo.gif', 'image/gif', null, true)],
      ));

      $commandBus = $this->createMock(CommandBusPort::class);
      $commandBus->expects(self::once())->method('dispatch')->willReturn(new AddFacilityAttachmentResult(
        $attachment->id,
        self::FACILITY_ID,
        $attachment->fileName,
        $attachment->mimeType,
        $attachment->size,
        null,
        $attachment->uploadedAt,
      ));

      $result = new FacilityMediaProcessor(
        $entityManager,
        $commandBus,
        $authorization,
        $security,
        $requestStack,
        new MultipartAttachmentGuard(),
        new RevisionGuard($requestStack),
      )->process(null, new Post(), ['facilityId' => self::FACILITY_ID]);

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
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->willReturn($facility);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/facilities/' . self::FACILITY_ID . '/attachments', 'POST'));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(AccessDeniedHttpException::class);

    new FacilityMediaProcessor(
      $entityManager,
      $commandBus,
      $authorization,
      $security,
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Post(), ['facilityId' => self::FACILITY_ID]);
  }

  #[Test]
  public function testDeleteRequiresIfMatchHeader(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $attachment = new FacilityAttachmentRecord();
    $attachment->id = 'attachment-id';
    $attachment->facility = $facility;
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
    $requestStack->push(Request::create('/api/facility-attachments/' . $attachment->id, 'DELETE'));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(PreconditionRequiredHttpException::class);

    new FacilityMediaProcessor(
      $entityManager,
      $commandBus,
      $authorization,
      $security,
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), ['id' => $attachment->id]);
  }

  #[Test]
  public function testDeleteDispatchesCommandWhenRevisionMatches(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $attachment = new FacilityAttachmentRecord();
    $attachment->id = 'attachment-id';
    $attachment->facility = $facility;
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
    $request = Request::create('/api/facility-attachments/' . $attachment->id, 'DELETE');
    $request->headers->set('If-Match', '"revision-1"');
    $requestStack->push($request);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $command): bool => $command instanceof DeleteFacilityAttachmentCommand
          && self::ORGANIZATION_ID === $command->organizationId
          && self::FACILITY_ID === $command->facilityId
          && 'attachment-id' === $command->attachmentId,
      ));

    $result = new FacilityMediaProcessor(
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
