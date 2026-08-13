<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Attachment\DeleteInterventionAttachment;

use DateTimeImmutable;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\{InterventionAttachmentRepositoryPort, InterventionResourceGatewayPort};
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Application\UseCase\Command\Attachment\DeleteInterventionAttachment\{DeleteInterventionAttachmentCommand, DeleteInterventionAttachmentHandler, DeleteInterventionAttachmentResult};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionAttachmentNotFoundException, InterventionNotFoundException};
use Intervention\Domain\Model\Attachment\InterventionAttachment;
use Intervention\Domain\ValueObject\InterventionAttachmentId;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\FileStoragePort;

/**
 * Test DeleteInterventionAttachmentHandlerTest.
 *
 * The handler is the AUTHORITATIVE enforcer of the phase-based write
 * permission (see `tests/Architecture/Unit/InterventionAuthorizationEnforcementTest`).
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteInterventionAttachmentHandler::class)]
final class DeleteInterventionAttachmentHandlerTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655446000';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446001';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655446002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655446003';

  private const string OTHER_INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655446005';

  #[Test]
  public function testInvokeDeletesRecordThenStorageObject(): void
  {
    $resourceManager = $this->resourceManager('in_progress');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    $attachment = $this->attachment(self::INTERVENTION_ID);

    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);
    $attachmentRepository->expects(self::once())->method('delete');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('delete')->with($attachment->storagePath());

    $handler = new DeleteInterventionAttachmentHandler(
      interventionResourceManager: $resourceManager,
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $result = $handler->__invoke(new DeleteInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));

    self::assertInstanceOf(DeleteInterventionAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
  }

  #[Test]
  public function testInvokeThrowsWhenInterventionNotFound(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(null);

    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('delete');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $handler = new DeleteInterventionAttachmentHandler(
      interventionResourceManager: new InterventionResourceManager($resources),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(InterventionNotFoundException::class);

    $handler->__invoke(new DeleteInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsInterventionNotFoundWhenTheCallerIsNotAMemberOfTheOwningOrganization(): void
  {
    // The scope gate runs BEFORE mutationPermission() derives the required
    // permission from the intervention's phase — that derivation can itself
    // throw a conflict on a published intervention, which would tell an
    // outsider both that the intervention exists and what state it is in.
    /** @var InterventionResourceGatewayPort&MockObject $resources */
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORG_ID, 'in_progress'),
    );
    $resources->expects(self::never())->method('interventionMutationContext');

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(false);
    $authorization->expects(self::never())->method('hasPermission');

    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('delete');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $handler = new DeleteInterventionAttachmentHandler(
      interventionResourceManager: new InterventionResourceManager($resources),
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(InterventionNotFoundException::class);

    $handler->__invoke(new DeleteInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsAccessDeniedWhenUserLacksThePhasePermission(): void
  {
    $resourceManager = $this->resourceManager('in_progress');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(false);

    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('delete');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $handler = new DeleteInterventionAttachmentHandler(
      interventionResourceManager: $resourceManager,
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(InterventionAccessDeniedException::class);

    $handler->__invoke(new DeleteInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentBelongsToAnotherIntervention(): void
  {
    $resourceManager = $this->resourceManager('in_progress');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    $attachment = $this->attachment(self::OTHER_INTERVENTION_ID);

    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);
    $attachmentRepository->expects(self::never())->method('delete');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $handler = new DeleteInterventionAttachmentHandler(
      interventionResourceManager: $resourceManager,
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(InterventionAttachmentNotFoundException::class);

    $handler->__invoke(new DeleteInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeRejectsAMalformedAttachmentId(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('findById');

    $handler = new DeleteInterventionAttachmentHandler(
      interventionResourceManager: $this->resourceManager('in_progress'),
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $this->createStub(FileStoragePort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new DeleteInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      attachmentId: 'not-a-uuid',
    ));
  }

  private function resourceManager(string $status): InterventionResourceManager
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $context = new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORG_ID, $status);
    $resources->method('interventionAssignmentContext')->willReturn($context);
    $resources->method('interventionMutationContext')->willReturn($context);

    return new InterventionResourceManager($resources);
  }

  private function attachment(string $interventionId): InterventionAttachment
  {
    return InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: $interventionId,
      fileName: 'evidence.jpg',
      storagePath: 'intervention/' . $interventionId . '/attachments/' . self::ATTACHMENT_ID . '_evidence.jpg',
      mimeType: 'image/jpeg',
      size: 10,
      uploadedAt: new DateTimeImmutable(),
    );
  }
}
