<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Post;
use Approval\Application\Contract\Gate\{ApprovalGateDecision, ApprovalGateRequest};
use Approval\Application\Port\Inbound\ApprovalGatePort;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment\{DecommissionEquipmentCommand, DecommissionEquipmentResult};
use Equipment\Domain\Exception\{EquipmentAlreadyDecommissionedException, EquipmentNotFoundException};
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Processor\Equipment\DecommissionEquipmentProcessor;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{JsonResponse, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, ConflictHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

use function json_decode;

#[CoversClass(DecommissionEquipmentProcessor::class)]
final class DecommissionEquipmentProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655455001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655455002';

  #[Test]
  public function testProcessThrowsAccessDeniedWhenPermissionMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655455010');

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

    $approvalGate = $this->createMock(ApprovalGatePort::class);
    $approvalGate->expects(self::never())->method('evaluate');

    $processor = new DecommissionEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      approvalGate: $approvalGate,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProcessMapsWrappedNotFoundToHttp404(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655455011');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new DecommissionEquipmentCommand(organizationId: self::ORG_ID, equipmentId: self::EQUIP_ID)),
      [EquipmentNotFoundException::withId(self::EQUIP_ID)],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new DecommissionEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      approvalGate: $this->applyNowGate(),
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProcessMapsWrappedAlreadyDecommissionedToHttp409(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655455012');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new DecommissionEquipmentCommand(organizationId: self::ORG_ID, equipmentId: self::EQUIP_ID)),
      [EquipmentAlreadyDecommissionedException::withId(self::EQUIP_ID)],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new DecommissionEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      approvalGate: $this->applyNowGate(),
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProcessReturnsDecommissionedEquipmentOnSuccess(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655455013');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $now = new DateTimeImmutable('2026-03-15T10:00:00+00:00');

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new DecommissionEquipmentResult(
      equipmentId: self::EQUIP_ID,
      organizationId: self::ORG_ID,
      facilityId: null,
      type: 'fire_extinguisher',
      subType: null,
      brand: null,
      model: null,
      serialNumber: null,
      locationLabel: null,
      status: 'decommissioned',
      installedAt: null,
      commissionedAt: null,
      tags: [],
      createdAt: $now,
      updatedAt: $now,
    ));

    $processor = new DecommissionEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      approvalGate: $this->applyNowGate(),
      security: $security,
    );

    $output = $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );

    self::assertInstanceOf(EquipmentOutput::class, $output);
    self::assertSame(self::EQUIP_ID, $output->id);
    self::assertSame('decommissioned', $output->status);
  }

  #[Test]
  public function testProcessReturns202WhenGateDefersTheAction(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655455014');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $expiresAt = new DateTimeImmutable('2026-04-01T00:00:00+00:00');
    $approvalGate = $this->createMock(ApprovalGatePort::class);
    $approvalGate->expects(self::once())
      ->method('evaluate')
      ->with(self::callback(static function (ApprovalGateRequest $request): bool {
        self::assertSame('equipment_decommission', $request->actionType);
        self::assertSame(self::EQUIP_ID, $request->subjectId);

        return true;
      }))
      ->willReturn(ApprovalGateDecision::deferred('request-1', 'pending', $expiresAt));

    $processor = new DecommissionEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      approvalGate: $approvalGate,
      security: $security,
    );

    $response = $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );

    self::assertInstanceOf(JsonResponse::class, $response);
    self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
    /** @var array{status: string, approvalRequestId: string, approvalStatus: string} $payload */
    $payload = json_decode((string) $response->getContent(), true);
    self::assertSame('pending_approval', $payload['status']);
    self::assertSame('request-1', $payload['approvalRequestId']);
    self::assertSame('pending', $payload['approvalStatus']);
  }

  private function applyNowGate(): ApprovalGatePort
  {
    $approvalGate = $this->createStub(ApprovalGatePort::class);
    $approvalGate->method('evaluate')->willReturn(ApprovalGateDecision::applyNow());

    return $approvalGate;
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
