<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment;

use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\{InterventionAttachmentRepositoryPort, InterventionResourceGatewayPort};
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment\{AddInterventionAttachmentCommand, AddInterventionAttachmentHandler, AddInterventionAttachmentResult};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Intervention\Domain\ValueObject\InterventionAttachmentId;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\FileStoragePort;

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

  #[Test]
  public function testInvokeStoresAttachmentSuccessfully(): void
  {
    $resourceManager = $this->resourceManager('in_progress');

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
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
  public function testInvokeThrowsAccessDeniedWhenUserLacksThePhasePermission(): void
  {
    $resourceManager = $this->resourceManager('in_progress');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
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

  private function resourceManager(string $status): InterventionResourceManager
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $context = new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORG_ID, $status);
    $resources->method('interventionAssignmentContext')->willReturn($context);
    $resources->method('interventionMutationContext')->willReturn($context);

    return new InterventionResourceManager($resources);
  }
}
