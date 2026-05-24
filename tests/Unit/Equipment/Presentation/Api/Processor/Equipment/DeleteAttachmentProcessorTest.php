<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Command\Equipment\DeleteAttachment\DeleteAttachmentCommand;
use Equipment\Domain\Exception\{AttachmentNotFoundException, EquipmentNotFoundException};
use Equipment\Presentation\Api\Processor\Equipment\DeleteAttachmentProcessor;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(DeleteAttachmentProcessor::class)]
final class DeleteAttachmentProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655454001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655454002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655454003';

  #[Test]
  public function testProcessThrowsAccessDeniedWhenPermissionMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655454010');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), self::ORG_ID, 'organization.equipment.write')
      ->willReturn(false);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new DeleteAttachmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: [
        'organizationId' => self::ORG_ID,
        'equipmentId' => self::EQUIP_ID,
        'attachmentId' => self::ATTACHMENT_ID,
      ],
    );
  }

  #[Test]
  public function testProcessMapsWrappedEquipmentNotFoundToHttp404(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655454011');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new DeleteAttachmentCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        attachmentId: self::ATTACHMENT_ID,
      )),
      [EquipmentNotFoundException::withId(self::EQUIP_ID)],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new DeleteAttachmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: [
        'organizationId' => self::ORG_ID,
        'equipmentId' => self::EQUIP_ID,
        'attachmentId' => self::ATTACHMENT_ID,
      ],
    );
  }

  #[Test]
  public function testProcessMapsWrappedAttachmentNotFoundToHttp404(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655454012');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new DeleteAttachmentCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        attachmentId: self::ATTACHMENT_ID,
      )),
      [AttachmentNotFoundException::withId(self::ATTACHMENT_ID)],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new DeleteAttachmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: [
        'organizationId' => self::ORG_ID,
        'equipmentId' => self::EQUIP_ID,
        'attachmentId' => self::ATTACHMENT_ID,
      ],
    );
  }

  #[Test]
  public function testProcessReturnsNullOnSuccess(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655454013');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch');

    $processor = new DeleteAttachmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: [
        'organizationId' => self::ORG_ID,
        'equipmentId' => self::EQUIP_ID,
        'attachmentId' => self::ATTACHMENT_ID,
      ],
    );

    self::assertNull($output);
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
