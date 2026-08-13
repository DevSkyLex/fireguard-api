<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment;

use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\{InterventionAttachmentRepositoryPort, InterventionResourceGatewayPort};
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment\{AddInterventionAttachmentCommand, AddInterventionAttachmentHandler, AddInterventionAttachmentResult};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionConflictException, InterventionNotFoundException, InterventionValidationException};
use Intervention\Domain\Model\Attachment\InterventionAttachment;
use Intervention\Domain\ValueObject\{InterventionAttachmentId, InterventionAttachmentKind};
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Attachment\{AttachmentConstraints, InvalidAttachmentException};

/**
 * Test AddInterventionAttachmentHandlerTest.
 *
 * The handler is the AUTHORITATIVE enforcer of the phase-based write
 * permission (see `tests/Architecture/Unit/InterventionAuthorizationEnforcementTest`).
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AddInterventionAttachmentHandler::class)]
final class AddInterventionAttachmentHandlerTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655446000';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446001';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655446002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655446003';

  private const string WORK_ITEM_ID = '550e8400-e29b-41d4-a716-446655446004';

  #[Test]
  public function testInvokeStoresAttachmentSuccessfully(): void
  {
    $resourceManager = $this->resourceManager('in_progress');

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.interventions.execute')
      ->willReturn(true);

    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InterventionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $resourceManager,
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      contents: 'jpg-content',
      mimeType: 'image/jpeg',
      size: 512,
      label: 'Execution evidence',
    ));

    self::assertInstanceOf(AddInterventionAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
    self::assertSame(self::INTERVENTION_ID, $result->interventionId);
  }

  #[Test]
  public function testInvokeThrowsWhenInterventionNotFound(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(null);

    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('save');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InterventionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: new InterventionResourceManager($resources),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InterventionNotFoundException::class);

    $handler->__invoke(new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      contents: 'content',
      mimeType: 'image/jpeg',
      size: 100,
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
    $attachmentRepository->expects(self::never())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InterventionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: new InterventionResourceManager($resources),
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InterventionNotFoundException::class);

    $handler->__invoke(new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      contents: 'content',
      mimeType: 'image/jpeg',
      size: 100,
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
    $attachmentRepository->expects(self::never())->method('save');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InterventionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $resourceManager,
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InterventionAccessDeniedException::class);

    $handler->__invoke(new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      contents: 'content',
      mimeType: 'image/jpeg',
      size: 100,
    ));
  }

  #[Test]
  public function testInvokeResolvesThePlanPermissionWhileDraft(): void
  {
    $resourceManager = $this->resourceManager('draft');

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.interventions.plan')
      ->willReturn(true);

    $attachmentRepository = $this->createStub(InterventionAttachmentRepositoryPort::class);
    $fileStorage = $this->createStub(FileStoragePort::class);

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InterventionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $resourceManager,
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $handler->__invoke(new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: 'plan.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 100,
    ));
  }

  #[Test]
  public function testInvokeDeletesFileWhenDatabaseSaveFails(): void
  {
    $resourceManager = $this->resourceManager('in_progress');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())
      ->method('save')
      ->willThrowException(new RuntimeException('Database error.'));

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');
    $fileStorage->expects(self::once())->method('delete');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InterventionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $resourceManager,
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Database error.');

    $handler->__invoke(new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      contents: 'content',
      mimeType: 'image/jpeg',
      size: 100,
    ));
  }

  #[Test]
  public function testInvokeAppliesBasenameToPreventPathTraversal(): void
  {
    $resourceManager = $this->resourceManager('in_progress');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    $attachmentRepository = $this->createStub(InterventionAttachmentRepositoryPort::class);

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())
      ->method('write')
      ->with(
        self::matchesRegularExpression('#^intervention/.+/attachments/.+_evil\.pdf$#'),
        self::anything(),
      );

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InterventionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $resourceManager,
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $handler->__invoke(new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: '../../etc/evil.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 7,
    ));
  }

  #[Test]
  public function testInvokeHonoursAClientSuppliedAttachmentIdInsteadOfMintingOne(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $this->resourceManager('in_progress'),
      authorization: $authorization,
      attachmentRepository: $this->createStub(InterventionAttachmentRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke($this->command(self::ATTACHMENT_ID));

    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
  }

  #[Test]
  public function testInvokeRejectsAMalformedClientSuppliedAttachmentId(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $this->resourceManager('in_progress'),
      authorization: $authorization,
      attachmentRepository: $this->createStub(InterventionAttachmentRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke($this->command('not-a-uuid'));
  }

  #[Test]
  public function testInvokeRejectsAnUploadWhenTheInterventionIsAtTheAttachmentCap(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn(null);
    $attachmentRepository->method('countByInterventionId')
      ->with(self::INTERVENTION_ID)
      ->willReturn(AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT);
    $attachmentRepository->expects(self::never())->method('save');

    // The cap must be refused BEFORE any byte reaches storage, otherwise a
    // rejected upload still grows the bucket.
    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InterventionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $this->resourceManager('in_progress'),
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InvalidAttachmentException::class);

    $handler->__invoke($this->command(null));
  }

  #[Test]
  public function testInvokeAcceptsAnUploadOneBelowTheAttachmentCap(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn(null);
    $attachmentRepository->method('countByInterventionId')
      ->willReturn(AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT - 1);
    $attachmentRepository->expects(self::once())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InterventionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $this->resourceManager('in_progress'),
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke($this->command(null));

    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
  }

  #[Test]
  public function testInvokeExemptsARetryOfAnExistingAttachmentFromTheCap(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    $existing = InterventionAttachment::create(
      id: new InterventionAttachmentId(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      storagePath: 'intervention/x/attachments/y_evidence.jpg',
      mimeType: 'image/jpeg',
      size: 512,
    );

    // A client-supplied id that already exists overwrites its own row: it adds
    // nothing to the bucket, so the count must not even be consulted.
    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($existing);
    $attachmentRepository->expects(self::never())->method('countByInterventionId');
    $attachmentRepository->expects(self::once())->method('save');

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $this->resourceManager('in_progress'),
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $result = $handler->__invoke($this->command(self::ATTACHMENT_ID));

    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
  }

  #[Test]
  public function testInvokeAcceptsAWorkItemIdBelongingToTheSameIntervention(): void
  {
    $resourceManager = $this->resourceManager('in_progress', workItemBelongsToIntervention: true);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn(null);
    $attachmentRepository->expects(self::once())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InterventionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $resourceManager,
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      contents: 'content',
      mimeType: 'image/jpeg',
      size: 100,
      workItemId: self::WORK_ITEM_ID,
    ));

    self::assertSame(self::WORK_ITEM_ID, $result->workItemId);
  }

  #[Test]
  public function testInvokeRejectsAWorkItemIdFromAnotherIntervention(): void
  {
    $resourceManager = $this->resourceManager('in_progress', workItemBelongsToIntervention: false);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $resourceManager,
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $this->expectException(InterventionValidationException::class);

    $handler->__invoke(new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      contents: 'content',
      mimeType: 'image/jpeg',
      size: 100,
      workItemId: self::WORK_ITEM_ID,
    ));
  }

  #[Test]
  public function testInvokeRejectsAnUnknownAttachmentKind(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $this->resourceManager('in_progress'),
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $this->expectException(InterventionValidationException::class);

    $handler->__invoke(new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      contents: 'content',
      mimeType: 'image/jpeg',
      size: 100,
      kind: 'not-a-real-kind',
    ));
  }

  #[Test]
  public function testInvokeRejectsASignatureUploadOutsideTheAllowedPhases(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    // "submitted" is mutable-checked BEFORE the signature phase gate — use
    // "planned" instead, which is mutable but not the submission phase.
    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $this->resourceManager('planned'),
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $this->expectException(InterventionConflictException::class);

    $handler->__invoke(new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: 'signature.png',
      contents: 'content',
      mimeType: 'image/png',
      size: 100,
      kind: 'signature',
    ));
  }

  #[Test]
  public function testInvokeRejectsAPdfAsASignature(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $this->resourceManager('in_progress'),
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $this->expectException(InterventionValidationException::class);

    $handler->__invoke(new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: 'signature.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 100,
      kind: 'signature',
    ));
  }

  #[Test]
  public function testInvokeAcceptsASignatureUploadWhileChangesAreRequested(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn(null);
    $attachmentRepository->method('findSignatureByInterventionId')->willReturn(null);
    $attachmentRepository->expects(self::once())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InterventionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $this->resourceManager('changes_requested'),
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: 'signature.png',
      contents: 'content',
      mimeType: 'image/png',
      size: 100,
      kind: 'signature',
    ));

    self::assertSame('signature', $result->kind);
  }

  #[Test]
  public function testInvokeReplacesThePreviousSignatureOnceTheNewOneIsSaved(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);

    $previousSignatureId = new InterventionAttachmentId('550e8400-e29b-41d4-a716-446655446099');
    $previousSignature = InterventionAttachment::create(
      id: $previousSignatureId,
      interventionId: self::INTERVENTION_ID,
      fileName: 'old-signature.png',
      storagePath: 'intervention/x/attachments/old-signature.png',
      mimeType: 'image/png',
      size: 256,
      kind: InterventionAttachmentKind::SIGNATURE,
    );

    /** @var InterventionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn(null);
    $attachmentRepository->method('findSignatureByInterventionId')->willReturn($previousSignature);
    // The cap check must not see the previous signature as an extra row: it
    // is being replaced, not added alongside.
    $attachmentRepository->method('countByInterventionId')->willReturn(AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT);
    $attachmentRepository->expects(self::once())->method('save');
    $attachmentRepository->expects(self::once())->method('delete')->with($previousSignatureId);

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');
    $fileStorage->expects(self::once())->method('delete')->with($previousSignature->storagePath());

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InterventionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInterventionAttachmentHandler(
      interventionResourceManager: $this->resourceManager('in_progress'),
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: 'new-signature.png',
      contents: 'content',
      mimeType: 'image/png',
      size: 100,
      kind: 'signature',
    ));

    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
  }

  private function command(?string $attachmentId): AddInterventionAttachmentCommand
  {
    return new AddInterventionAttachmentCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      contents: 'jpg-content',
      mimeType: 'image/jpeg',
      size: 512,
      label: 'Execution evidence',
      attachmentId: $attachmentId,
    );
  }

  private function resourceManager(string $status, ?bool $workItemBelongsToIntervention = null): InterventionResourceManager
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $context = new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORG_ID, $status);
    $resources->method('interventionAssignmentContext')->willReturn($context);
    $resources->method('interventionMutationContext')->willReturn($context);
    if (null !== $workItemBelongsToIntervention) {
      $resources->method('workItemBelongsToIntervention')->willReturn($workItemBelongsToIntervention);
    }

    return new InterventionResourceManager($resources);
  }
}
