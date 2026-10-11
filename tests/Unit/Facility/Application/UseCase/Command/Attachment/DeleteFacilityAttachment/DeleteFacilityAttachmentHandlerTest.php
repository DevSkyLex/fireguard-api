<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment;

use DateTimeImmutable;
use Facility\Application\Port\Outbound\FacilityPlanReferenceCleanupPort;
use Facility\Application\Port\Outbound\Internal\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment\{DeleteFacilityAttachmentCommand, DeleteFacilityAttachmentHandler, DeleteFacilityAttachmentResult};
use Facility\Domain\Exception\FacilityAccessDeniedException;
use Facility\Domain\Exception\{FacilityAttachmentNotFoundException, FacilityNotFoundException};
use Facility\Domain\Model\Attachment\{FacilityAttachment, FacilityAttachmentRestoredState};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{FileCleanupPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

#[CoversClass(DeleteFacilityAttachmentHandler::class)]
final class DeleteFacilityAttachmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446001';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655446002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655446003';

  private const string OTHER_FACILITY_ID = '550e8400-e29b-41d4-a716-446655446004';

  #[Test]
  public function testInvokeDeletesRecordThenStorageObject(): void
  {
    $facility = $this->facility();
    $attachment = $this->attachment();

    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);
    $attachmentRepository->expects(self::once())->method('delete');

    /** @var FileCleanupPort&MockObject $cleanup */
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::once())->method('request')->with($attachment->file()->storagePath);

    $handler = new DeleteFacilityAttachmentHandler(
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      cleanup: $cleanup,
      referenceCleanup: $this->createStub(FacilityPlanReferenceCleanupPort::class),
      transactionManager: $this->transactionManager(),
    );

    $result = $handler->__invoke(new DeleteFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));

    self::assertInstanceOf(DeleteFacilityAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityNotFound(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn(null);

    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('delete');

    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::never())->method('request');

    $handler = new DeleteFacilityAttachmentHandler(
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      cleanup: $cleanup,
      referenceCleanup: $this->createStub(FacilityPlanReferenceCleanupPort::class),
      transactionManager: $this->transactionManager(),
    );

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new DeleteFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentBelongsToAnotherFacility(): void
  {
    $facility = $this->facility();
    $attachment = FacilityAttachment::reconstitute(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::OTHER_FACILITY_ID),
      fileName: 'floor-plan.pdf',
      storagePath: 'facility/' . self::OTHER_FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_floor-plan.pdf',
      mimeType: 'application/pdf',
      size: 10,
      state: new FacilityAttachmentRestoredState(new DateTimeImmutable()),
    );

    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);

    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);
    $attachmentRepository->expects(self::never())->method('delete');

    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::never())->method('request');

    $handler = new DeleteFacilityAttachmentHandler(
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      cleanup: $cleanup,
      referenceCleanup: $this->createStub(FacilityPlanReferenceCleanupPort::class),
      transactionManager: $this->transactionManager(),
    );

    $this->expectException(FacilityAttachmentNotFoundException::class);

    $handler->__invoke(new DeleteFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentForMalformedIdentifiers(): void
  {
    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::never())->method('findById');

    /** @var FileCleanupPort&MockObject $cleanup */
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::never())->method('request');

    $handler = new DeleteFacilityAttachmentHandler(
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      facilityRepository: $facilityRepository,
      attachmentRepository: $this->createStub(FacilityAttachmentRepositoryPort::class),
      cleanup: $cleanup,
      referenceCleanup: $this->createStub(FacilityPlanReferenceCleanupPort::class),
      transactionManager: $this->transactionManager(),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new DeleteFacilityAttachmentCommand(
      organizationId: 'not-a-uuid',
      facilityId: 'also-not-a-uuid',
      attachmentId: 'still-not-a-uuid',
    ));
  }

  /**
   * Method authorizationPrecedesRevisionAndDurableCleanup
   *
   * @param class-string<Throwable> $exceptionClass expected denial contract
   */
  #[Test]
  #[DataProvider('authorizationDenials')]
  public function authorizationPrecedesRevisionAndDurableCleanup(OrganizationAccessDecision $decision, string $exceptionClass, string $message): void
  {
    $facilities = $this->createStub(FacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility());
    $attachments = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachments->expects(self::once())->method('lock');
    $attachments->expects(self::once())->method('findById')->willReturn($this->attachment());
    $attachments->expects(self::never())->method('delete');
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::never())->method('request');
    $references = $this->createMock(FacilityPlanReferenceCleanupPort::class);
    $references->expects(self::never())->method('clearForAttachment');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with('writer', self::ORG_ID, 'organization.facilities.write')->willReturn($decision);
    $handler = new DeleteFacilityAttachmentHandler($facilities, $attachments, $cleanup, $references, $this->transactionManager(), $authorization);
    $this->expectException($exceptionClass);
    $this->expectExceptionMessage($message);
    $handler(new DeleteFacilityAttachmentCommand(self::ORG_ID, self::FACILITY_ID, self::ATTACHMENT_ID, userId: 'writer', expectedRevision: 99));
  }

  /**
   * Method authorizationDenials
   *
   * @return iterable<string, array{OrganizationAccessDecision, class-string<Throwable>, string}>
   */
  public static function authorizationDenials(): iterable
  {
    yield 'outside scope' => [OrganizationAccessDecision::OUTSIDE_SCOPE, FacilityAttachmentNotFoundException::class, 'Attachment not found.'];
    yield 'missing permission' => [OrganizationAccessDecision::MISSING_PERMISSION, FacilityAccessDeniedException::class, 'Missing organization.facilities.write permission.'];
  }

  private function transactionManager(): TransactionManagerPort
  {
    $port = $this->createStub(TransactionManagerPort::class);
    $port->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());

    return $port;
  }

  private function facility(): Facility
  {
    return Facility::create(
      id: FacilityId::fromString(self::FACILITY_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORG_ID),
      type: FacilityType::SITE,
      name: new FacilityName('Main Site'),
    );
  }

  private function attachment(): FacilityAttachment
  {
    return FacilityAttachment::reconstitute(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'floor-plan.pdf',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_floor-plan.pdf',
      mimeType: 'application/pdf',
      size: 10,
      state: new FacilityAttachmentRestoredState(new DateTimeImmutable()),
    );
  }
}
