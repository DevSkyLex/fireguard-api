<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Query\Equipment\ListEquipmentAttachments\{ListEquipmentAttachmentsQuery, ListEquipmentAttachmentsResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Presentation\Api\Dto\Output\Equipment\AttachmentOutput;
use Equipment\Presentation\Api\Provider\Equipment\ListEquipmentAttachmentsProvider;
use InvalidArgumentException;
use LogicException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

#[CoversClass(ListEquipmentAttachmentsProvider::class)]
final class ListEquipmentAttachmentsProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655459001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655459002';

  #[Test]
  public function testProvideThrowsAccessDeniedWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListEquipmentAttachmentsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideThrowsAccessDeniedWhenPermissionMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655459010');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), self::ORG_ID, 'organization.equipment.read')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new ListEquipmentAttachmentsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenOrganizationIsOutsideCallerScope(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655459020');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), self::ORG_ID, 'organization.equipment.read')
      ->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new ListEquipmentAttachmentsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideMapsWrappedEquipmentNotFoundToHttp404(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655459011');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new ListEquipmentAttachmentsQuery(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
      )),
      [EquipmentNotFoundException::withId(self::EQUIP_ID)],
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new ListEquipmentAttachmentsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideReturnsEmptyListWhenNoAttachments(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655459012');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(ListEquipmentAttachmentsQuery::class))
      ->willReturn(new ListEquipmentAttachmentsResult(attachments: []));

    $provider = new ListEquipmentAttachmentsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );

    self::assertSame([], $output);
  }

  #[Test]
  public function testProvideMapsResultToAttachmentOutputList(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655459013');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $attachmentId = '550e8400-e29b-41d4-a716-446655459099';

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new ListEquipmentAttachmentsResult(attachments: [
        [
          'id' => $attachmentId,
          'fileName' => 'report.pdf',
          'mimeType' => 'application/pdf',
          'size' => 12345,
          'label' => 'Inspection report',
          'uploadedAt' => '2026-03-15T10:00:00+00:00',
        ],
      ]));

    $provider = new ListEquipmentAttachmentsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );

    self::assertCount(1, $output);
    self::assertInstanceOf(AttachmentOutput::class, $output[0]);
    self::assertSame($attachmentId, $output[0]->id);
    self::assertSame(self::EQUIP_ID, $output[0]->equipmentId);
    self::assertSame('report.pdf', $output[0]->fileName);
    self::assertSame('application/pdf', $output[0]->mimeType);
    self::assertSame(12345, $output[0]->size);
    self::assertSame('Inspection report', $output[0]->label);
  }

  #[Test]
  public function testProvideThrowsBadRequestWhenUriVariablesAreMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655459012'));

    $provider = new ListEquipmentAttachmentsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(operation: new GetCollection(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProvideMapsADirectEquipmentNotFoundToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->providerThrowing(EquipmentNotFoundException::withId(self::EQUIP_ID))->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideMapsADirectInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->providerThrowing(new InvalidArgumentException('Invalid equipment identifier.'))->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideMapsAWrappedInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->providerThrowing(
      MessengerRuntimeException::wrap($this->handlerFailure(new InvalidArgumentException('Invalid equipment identifier.'))),
    )->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideRethrowsAMessengerFailureItCannotClassify(): void
  {
    $this->expectException(MessengerRuntimeException::class);

    $this->providerThrowing(
      MessengerRuntimeException::wrap($this->handlerFailure(new LogicException('Unexpected handler failure.'))),
    )->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  private function handlerFailure(Throwable $wrapped): HandlerFailedException
  {
    return new HandlerFailedException(
      new Envelope(new ListEquipmentAttachmentsQuery(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
      )),
      [$wrapped],
    );
  }

  private function providerThrowing(Throwable $exception): ListEquipmentAttachmentsProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655459012'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($exception);

    return new ListEquipmentAttachmentsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
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
