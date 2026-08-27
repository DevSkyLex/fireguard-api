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
use Symfony\Component\HttpFoundation\{JsonResponse, Response};
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException
};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

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
      ->method('resolveAccess')
      ->with($user->getId(), self::ORG_ID, 'organization.equipment.write')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

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
  public function testProcessThrowsNotFoundWhenOrganizationIsOutsideCallersScope(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655455015');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

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

    // Not AccessDeniedHttpException: a 403 for a caller outside the organization's
    // scope would confirm the record exists across an organization boundary.
    try {
      $processor->process(
        data: null,
        operation: new Post(),
        uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
      );
      self::fail('Expected NotFoundHttpException to be thrown.');
    } catch (NotFoundHttpException $exception) {
      self::assertSame('Organization not found.', $exception->getMessage());
    }
  }

  #[Test]
  public function testProcessMapsWrappedNotFoundToHttp404(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655455011');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

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
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

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
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

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
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

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

  #[Test]
  public function testProcessThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new DecommissionEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      approvalGate: $this->applyNowGate(),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $processor->process(data: null, operation: new Post(), uriVariables: $this->decommissionUriVariables());
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

    $processor = new DecommissionEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      approvalGate: $this->applyNowGate(),
      security: $this->decommissionSecurity(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(data: null, operation: new Post(), uriVariables: $uriVariables);
  }

  #[Test]
  public function testProcessMapsADirectNotFoundToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->decommissionProcessorThrowing(EquipmentNotFoundException::withId(self::EQUIP_ID))
      ->process(data: null, operation: new Post(), uriVariables: $this->decommissionUriVariables());
  }

  #[Test]
  public function testProcessMapsADirectDecommissionedConflictToHttp409(): void
  {
    $this->expectException(ConflictHttpException::class);

    $this->decommissionProcessorThrowing(EquipmentAlreadyDecommissionedException::withId(self::EQUIP_ID))
      ->process(data: null, operation: new Post(), uriVariables: $this->decommissionUriVariables());
  }

  #[Test]
  public function testProcessMapsADirectInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Equipment still has open interventions.');

    $this->decommissionProcessorThrowing(new InvalidArgumentException('Equipment still has open interventions.'))
      ->process(data: null, operation: new Post(), uriVariables: $this->decommissionUriVariables());
  }

  #[Test]
  public function testProcessMapsAWrappedInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->decommissionProcessorThrowing(
      $this->decommissionWrapped(new InvalidArgumentException('Equipment still has open interventions.')),
    )->process(data: null, operation: new Post(), uriVariables: $this->decommissionUriVariables());
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $this->expectException(MessengerRuntimeException::class);

    $this->decommissionProcessorThrowing($this->decommissionWrapped(new RuntimeException('database is down')))
      ->process(data: null, operation: new Post(), uriVariables: $this->decommissionUriVariables());
  }

  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function incompleteUriVariablesProvider(): iterable
  {
    yield 'no variables' => [[]];
    yield 'blank organizationId' => [['organizationId' => '', 'equipmentId' => self::EQUIP_ID]];
    yield 'missing equipmentId' => [['organizationId' => self::ORG_ID]];
    yield 'blank equipmentId' => [['organizationId' => self::ORG_ID, 'equipmentId' => '']];
  }

  private function applyNowGate(): ApprovalGatePort
  {
    $approvalGate = $this->createStub(ApprovalGatePort::class);
    $approvalGate->method('evaluate')->willReturn(ApprovalGateDecision::applyNow());

    return $approvalGate;
  }

  /**
   * @return array<string, string>
   */
  private function decommissionUriVariables(): array
  {
    return ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID];
  }

  private function decommissionSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655455010'));

    return $security;
  }

  private function decommissionWrapped(Throwable $failure): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new DecommissionEquipmentCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
      )),
      [$failure],
    ));
  }

  private function decommissionProcessorThrowing(Throwable $failure): DecommissionEquipmentProcessor
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    return new DecommissionEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      approvalGate: $this->applyNowGate(),
      security: $this->decommissionSecurity(),
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
