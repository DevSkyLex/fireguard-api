<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Attachment\GetInterventionAttachmentContent;

use DateTimeImmutable;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\{InterventionAttachmentRepositoryPort, InterventionResourceGatewayPort};
use Intervention\Application\UseCase\Query\Attachment\GetInterventionAttachmentContent\{GetInterventionAttachmentContentHandler, GetInterventionAttachmentContentQuery, GetInterventionAttachmentContentResult};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionAttachmentNotFoundException, InterventionNotFoundException};
use Intervention\Domain\Model\Attachment\InterventionAttachment;
use Intervention\Domain\ValueObject\InterventionAttachmentId;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test GetInterventionAttachmentContentHandlerTest.
 *
 * The handler is the AUTHORITATIVE enforcer of the flat
 * `organization.interventions.read` permission for the download route —
 * deliberately WITHOUT the phase-based write restriction the upload/delete
 * handlers apply, since a published intervention's evidence must stay
 * downloadable.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetInterventionAttachmentContentHandler::class)]
final class GetInterventionAttachmentContentHandlerTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655446000';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446001';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655446002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655446003';

  #[Test]
  public function testInvokeReturnsTheStoredBytesWhenReadPermissionIsGranted(): void
  {
    $attachmentRepository = $this->createStub(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($this->attachment());

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORG_ID, 'published'),
    );

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $fileStorage = $this->createStub(FileStoragePort::class);
    $fileStorage->method('read')->willReturn('JPEG-BYTES');

    $handler = new GetInterventionAttachmentContentHandler(
      attachmentRepository: $attachmentRepository,
      resources: $resources,
      authorization: $authorization,
      fileStorage: $fileStorage,
    );

    $result = $handler->__invoke(new GetInterventionAttachmentContentQuery(
      userId: self::USER_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));

    self::assertInstanceOf(GetInterventionAttachmentContentResult::class, $result);
    self::assertSame('JPEG-BYTES', $result->contents);
    self::assertSame('evidence.jpg', $result->fileName);
    self::assertSame('image/jpeg', $result->mimeType);
    self::assertSame(10, $result->size);
  }

  #[Test]
  public function testInvokeAllowsDownloadOnAPublishedImmutableIntervention(): void
  {
    // Deliberately NOT the phase-based mutation gate: a `published` status
    // must not block the read, unlike AddInterventionAttachmentHandler.
    $attachmentRepository = $this->createStub(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($this->attachment());

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORG_ID, 'published'),
    );

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $fileStorage = $this->createStub(FileStoragePort::class);
    $fileStorage->method('read')->willReturn('JPEG-BYTES');

    $handler = new GetInterventionAttachmentContentHandler(
      attachmentRepository: $attachmentRepository,
      resources: $resources,
      authorization: $authorization,
      fileStorage: $fileStorage,
    );

    $result = $handler->__invoke(new GetInterventionAttachmentContentQuery(
      userId: self::USER_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));

    self::assertSame('JPEG-BYTES', $result->contents);
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentIsNotFound(): void
  {
    $attachmentRepository = $this->createStub(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn(null);

    $handler = new GetInterventionAttachmentContentHandler(
      attachmentRepository: $attachmentRepository,
      resources: $this->createStub(InterventionResourceGatewayPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
    );

    $this->expectException(InterventionAttachmentNotFoundException::class);

    $handler->__invoke(new GetInterventionAttachmentContentQuery(
      userId: self::USER_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenTheOwningInterventionIsNotFound(): void
  {
    $attachmentRepository = $this->createStub(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($this->attachment());

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(null);

    $handler = new GetInterventionAttachmentContentHandler(
      attachmentRepository: $attachmentRepository,
      resources: $resources,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
    );

    $this->expectException(InterventionNotFoundException::class);

    $handler->__invoke(new GetInterventionAttachmentContentQuery(
      userId: self::USER_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeNeverReadsTheFileWhenReadPermissionIsMissing(): void
  {
    $attachmentRepository = $this->createStub(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($this->attachment());

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORG_ID, 'in_progress'),
    );

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('read');

    $handler = new GetInterventionAttachmentContentHandler(
      attachmentRepository: $attachmentRepository,
      resources: $resources,
      authorization: $authorization,
      fileStorage: $fileStorage,
    );

    $this->expectException(InterventionAccessDeniedException::class);

    $handler->__invoke(new GetInterventionAttachmentContentQuery(
      userId: self::USER_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsTheAttachmentNotFoundExceptionWhenTheOrganizationIsOutsideTheCallersScope(): void
  {
    $attachmentRepository = $this->createStub(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($this->attachment());

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORG_ID, 'in_progress'),
    );

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('read');

    $handler = new GetInterventionAttachmentContentHandler(
      attachmentRepository: $attachmentRepository,
      resources: $resources,
      authorization: $authorization,
      fileStorage: $fileStorage,
    );

    // OUTSIDE_SCOPE maps to the SAME not-found exception an unknown
    // attachment id produces, so the 403/404 split stops being an
    // existence oracle across organizations.
    $this->expectException(InterventionAttachmentNotFoundException::class);

    $handler->__invoke(new GetInterventionAttachmentContentQuery(
      userId: self::USER_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsOnAMalformedAttachmentId(): void
  {
    $handler = new GetInterventionAttachmentContentHandler(
      attachmentRepository: $this->createStub(InterventionAttachmentRepositoryPort::class),
      resources: $this->createStub(InterventionResourceGatewayPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new GetInterventionAttachmentContentQuery(
      userId: self::USER_ID,
      attachmentId: 'not-a-uuid',
    ));
  }

  private function attachment(): InterventionAttachment
  {
    return InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      storagePath: 'intervention/' . self::INTERVENTION_ID . '/attachments/' . self::ATTACHMENT_ID . '_evidence.jpg',
      mimeType: 'image/jpeg',
      size: 10,
      uploadedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
}
