<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\UseCase\Command\Equipment\AddAttachment\{AddAttachmentCommand, AddAttachmentResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Presentation\Api\Dto\Input\Equipment\AddAttachmentInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\AttachmentOutput;
use Equipment\Presentation\Api\Processor\Equipment\AddAttachmentProcessor;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

use function base64_encode;

#[CoversClass(AddAttachmentProcessor::class)]
final class AddAttachmentProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655458001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655458002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655458003';

  #[Test]
  public function testProcessThrowsAccessDeniedWhenPermissionMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655458010');

    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), self::ORG_ID, 'organization.equipment.write')
      ->willReturn(false);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $input = new AddAttachmentInput();
    $input->fileName = 'report.pdf';
    $input->content = base64_encode('PDF content');
    $input->mimeType = 'application/pdf';

    $processor = new AddAttachmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenContentIsNotValidBase64(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655458011');

    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $input = new AddAttachmentInput();
    $input->fileName = 'report.pdf';
    $input->content = '!!! this is not base64 !!!';
    $input->mimeType = 'application/pdf';

    $processor = new AddAttachmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('File content must be valid base64-encoded data.');

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProcessMapsWrappedNotFoundToHttp404(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655458012');

    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $input = new AddAttachmentInput();
    $input->fileName = 'report.pdf';
    $input->content = base64_encode('PDF content');
    $input->mimeType = 'application/pdf';

    $handlerFailure = new HandlerFailedException(
      new Envelope(new AddAttachmentCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        fileName: 'report.pdf',
        contents: 'PDF content',
        mimeType: 'application/pdf',
        size: 11,
      )),
      [EquipmentNotFoundException::withId(self::EQUIP_ID)],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new AddAttachmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProcessReturnsAttachmentOutputOnSuccess(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655458013');

    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $input = new AddAttachmentInput();
    $input->fileName = 'inspection.pdf';
    $input->content = base64_encode('PDF content here');
    $input->mimeType = 'application/pdf';
    $input->label = 'Inspection report';

    $now = new DateTimeImmutable('2026-03-15T10:00:00+00:00');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new AddAttachmentResult(
      attachmentId: self::ATTACHMENT_ID,
      equipmentId: self::EQUIP_ID,
      fileName: 'inspection.pdf',
      mimeType: 'application/pdf',
      size: 16,
      label: 'Inspection report',
      uploadedAt: $now,
    ));

    $processor = new AddAttachmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );

    self::assertInstanceOf(AttachmentOutput::class, $output);
    self::assertSame(self::ATTACHMENT_ID, $output->id);
    self::assertSame('inspection.pdf', $output->fileName);
    self::assertSame('application/pdf', $output->mimeType);
    self::assertSame('Inspection report', $output->label);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
