<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Command\Equipment\DeleteAttachment\DeleteAttachmentCommand;
use Equipment\Domain\Exception\{AttachmentNotFoundException, EquipmentNotFoundException};
use Equipment\Presentation\Api\Processor\Equipment\DeleteAttachmentProcessor;
use InvalidArgumentException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException
};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

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
      ->method('resolveAccess')
      ->with($user->getId(), self::ORG_ID, 'organization.equipment.write')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

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
  public function testProcessThrowsNotFoundWhenOrganizationIsOutsideCallersScope(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655454014');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new DeleteAttachmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    // Not AccessDeniedHttpException: a 403 for a caller outside the organization's
    // scope would confirm the record exists across an organization boundary.
    try {
      $processor->process(
        data: null,
        operation: new Post(),
        uriVariables: [
          'organizationId' => self::ORG_ID,
          'equipmentId' => self::EQUIP_ID,
          'attachmentId' => self::ATTACHMENT_ID,
        ],
      );
      self::fail('Expected NotFoundHttpException to be thrown.');
    } catch (NotFoundHttpException $exception) {
      self::assertSame('Organization not found.', $exception->getMessage());
    }
  }

  #[Test]
  public function testProcessMapsWrappedEquipmentNotFoundToHttp404(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655454011');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

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
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

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
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

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

  #[Test]
  public function testProcessThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new DeleteAttachmentProcessor(
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $processor->process(data: null, operation: new Post(), uriVariables: $this->deleteUriVariables());
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('incompleteUriVariablesProvider')]
  public function testProcessThrowsBadRequestWhenUriVariablesAreIncomplete(array $uriVariables): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new DeleteAttachmentProcessor(
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $this->deleteSecurity(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(data: null, operation: new Post(), uriVariables: $uriVariables);
  }

  #[Test]
  public function testProcessMapsADirectEquipmentNotFoundToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->deleteProcessorThrowing(EquipmentNotFoundException::withId(self::EQUIP_ID))
      ->process(data: null, operation: new Post(), uriVariables: $this->deleteUriVariables());
  }

  #[Test]
  public function testProcessMapsADirectAttachmentNotFoundToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->deleteProcessorThrowing(AttachmentNotFoundException::withId(self::ATTACHMENT_ID))
      ->process(data: null, operation: new Post(), uriVariables: $this->deleteUriVariables());
  }

  #[Test]
  public function testProcessMapsADirectInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Attachment belongs to another equipment.');

    $this->deleteProcessorThrowing(new InvalidArgumentException('Attachment belongs to another equipment.'))
      ->process(data: null, operation: new Post(), uriVariables: $this->deleteUriVariables());
  }

  #[Test]
  public function testProcessMapsAWrappedInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->deleteProcessorThrowing(
      $this->deleteWrapped(new InvalidArgumentException('Attachment belongs to another equipment.')),
    )->process(data: null, operation: new Post(), uriVariables: $this->deleteUriVariables());
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $this->expectException(MessengerRuntimeException::class);

    $this->deleteProcessorThrowing($this->deleteWrapped(new RuntimeException('storage is down')))
      ->process(data: null, operation: new Post(), uriVariables: $this->deleteUriVariables());
  }

  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function incompleteUriVariablesProvider(): iterable
  {
    yield 'no variables' => [[]];
    yield 'missing attachmentId' => [['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID]];
    yield 'blank attachmentId' => [['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID, 'attachmentId' => '']];
    yield 'blank equipmentId' => [['organizationId' => self::ORG_ID, 'equipmentId' => '', 'attachmentId' => self::ATTACHMENT_ID]];
  }

  /**
   * @return array<string, string>
   */
  private function deleteUriVariables(): array
  {
    return [
      'organizationId' => self::ORG_ID,
      'equipmentId' => self::EQUIP_ID,
      'attachmentId' => self::ATTACHMENT_ID,
    ];
  }

  private function deleteSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655454010'));

    return $security;
  }

  private function deleteWrapped(Throwable $failure): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new DeleteAttachmentCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        attachmentId: self::ATTACHMENT_ID,
      )),
      [$failure],
    ));
  }

  private function deleteProcessorThrowing(Throwable $failure): DeleteAttachmentProcessor
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    return new DeleteAttachmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $this->deleteSecurity(),
    );
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
