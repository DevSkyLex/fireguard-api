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
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Shared\Presentation\Api\Http\RevisionGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, PreconditionRequiredHttpException};

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
        ->method('resolveAccess')
        ->with('user-id', self::ORGANIZATION_ID, 'organization.facilities.write')
        ->willReturn(OrganizationAccessDecision::GRANTED);

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
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

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
  public function testUploadReportsACallerOutsideTheOrganizationAsFacilityNotFound(): void
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
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/facilities/' . self::FACILITY_ID . '/attachments', 'POST'));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Facility not found.');

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
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

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
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

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

  #[Test]
  public function testUploadRejectsAMissingFacilityIdUriVariable(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->processor($this->wrappingEntityManager(), new RequestStack(), true, $this->userSecurity())
      ->process(null, new Post(), []);
  }

  #[Test]
  public function testUploadThrowsWhenTheFacilityIsMissing(): void
  {
    $entityManager = $this->wrappingEntityManager();
    $entityManager->method('find')->willReturn(null);

    $this->expectException(NotFoundHttpException::class);

    $this->processor($entityManager, new RequestStack(), true, $this->userSecurity())
      ->process(null, new Post(), ['facilityId' => self::FACILITY_ID]);
  }

  #[Test]
  public function testUploadRejectsAnUnauthenticatedUser(): void
  {
    $entityManager = $this->wrappingEntityManager();
    $entityManager->method('find')->willReturn($this->facilityRecord());

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $this->expectException(AccessDeniedHttpException::class);

    $this->processor($entityManager, new RequestStack(), true, $security)
      ->process(null, new Post(), ['facilityId' => self::FACILITY_ID]);
  }

  #[Test]
  public function testUploadRejectsAMissingRequest(): void
  {
    $entityManager = $this->wrappingEntityManager();
    $entityManager->method('find')->willReturn($this->facilityRecord());

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Request payload is required.');

    $this->processor($entityManager, new RequestStack(), true, $this->userSecurity())
      ->process(null, new Post(), ['facilityId' => self::FACILITY_ID]);
  }

  #[Test]
  public function testUploadThrowsWhenTheStoredAttachmentCannotBeReloaded(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'facility-attachment-');
    self::assertIsString($path);
    file_put_contents($path, (string) base64_decode(self::MINIMAL_GIF_BASE64, true));

    try {
      $facility = $this->facilityRecord();
      $entityManager = $this->wrappingEntityManager();
      $entityManager->method('find')->willReturnCallback(
        static fn (string $class): ?FacilityRecord => FacilityRecord::class === $class ? $facility : null,
      );

      $requestStack = new RequestStack();
      $requestStack->push(Request::create(
        '/api/facilities/' . self::FACILITY_ID . '/attachments',
        'POST',
        [],
        [],
        ['file' => new UploadedFile($path, 'photo.gif', 'image/gif', null, true)],
      ));

      $commandBus = $this->createStub(CommandBusPort::class);
      $commandBus->method('dispatch')->willReturn(new AddFacilityAttachmentResult(
        'attachment-id',
        self::FACILITY_ID,
        'photo.gif',
        'image/gif',
        5,
        null,
        new DateTimeImmutable(),
      ));

      $this->expectException(NotFoundHttpException::class);
      $this->expectExceptionMessage('Uploaded attachment not found.');

      new FacilityMediaProcessor(
        $entityManager,
        $commandBus,
        $this->grantingAuthorization(true),
        $this->userSecurity(),
        $requestStack,
        new MultipartAttachmentGuard(),
        new RevisionGuard($requestStack),
      )->process(null, new Post(), ['facilityId' => self::FACILITY_ID]);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testDeleteRejectsANonStringAttachmentId(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/facility-attachments/x', 'DELETE'));

    $this->expectException(NotFoundHttpException::class);

    $this->processor($this->wrappingEntityManager(), $requestStack, true, $this->userSecurity())
      ->process(null, new Delete(), []);
  }

  #[Test]
  public function testDeleteThrowsWhenTheAttachmentRecordIsMissing(): void
  {
    $entityManager = $this->wrappingEntityManager();
    $entityManager->method('find')->willReturn(null);

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/facility-attachments/attachment-id', 'DELETE'));

    $this->expectException(NotFoundHttpException::class);

    $this->processor($entityManager, $requestStack, true, $this->userSecurity())
      ->process(null, new Delete(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function testDeleteRejectsWhenMissingPermission(): void
  {
    $attachment = new FacilityAttachmentRecord();
    $attachment->id = 'attachment-id';
    $attachment->facility = $this->facilityRecord();
    $attachment->revision = 1;

    $entityManager = $this->wrappingEntityManager();
    $entityManager->method('find')->willReturn($attachment);

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/facility-attachments/attachment-id', 'DELETE'));

    $this->expectException(AccessDeniedHttpException::class);

    $this->processor($entityManager, $requestStack, false, $this->userSecurity())
      ->process(null, new Delete(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function testDeleteReportsACallerOutsideTheOrganizationAsAttachmentNotFound(): void
  {
    $attachment = new FacilityAttachmentRecord();
    $attachment->id = 'attachment-id';
    $attachment->facility = $this->facilityRecord();
    $attachment->revision = 1;

    $entityManager = $this->wrappingEntityManager();
    $entityManager->method('find')->willReturn($attachment);

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/facility-attachments/attachment-id', 'DELETE'));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Attachment not found.');

    new FacilityMediaProcessor(
      $entityManager,
      $commandBus,
      $authorization,
      $this->userSecurity(),
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), ['id' => 'attachment-id']);
  }

  private function facilityRecord(): FacilityRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;

    return $facility;
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

  private function grantingAuthorization(bool $granted): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(
      $granted ? OrganizationAccessDecision::GRANTED : OrganizationAccessDecision::MISSING_PERMISSION,
    );

    return $authorization;
  }

  private function userSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return $security;
  }

  private function processor(
    EntityManagerInterface $entityManager,
    RequestStack $requestStack,
    bool $granted,
    Security $security,
  ): FacilityMediaProcessor {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    return new FacilityMediaProcessor(
      $entityManager,
      $commandBus,
      $this->grantingAuthorization($granted),
      $security,
      $requestStack,
      new MultipartAttachmentGuard(),
      new RevisionGuard($requestStack),
    );
  }
}
