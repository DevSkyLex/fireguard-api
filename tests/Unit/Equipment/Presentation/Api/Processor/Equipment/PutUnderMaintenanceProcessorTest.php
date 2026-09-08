<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\UseCase\Command\Equipment\PutUnderMaintenance\{PutUnderMaintenanceCommand, PutUnderMaintenanceResult};
use Equipment\Domain\Exception\{EquipmentAlreadyDecommissionedException, EquipmentNotFoundException};
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Factory\EquipmentOutputFactory;
use Equipment\Presentation\Api\Processor\Equipment\PutUnderMaintenanceProcessor;
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
  ConflictHttpException,
  NotFoundHttpException
};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

#[CoversClass(PutUnderMaintenanceProcessor::class)]
final class PutUnderMaintenanceProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655453001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655453002';

  #[Test]
  public function testProcessThrowsAccessDeniedWhenPermissionMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655453010');

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

    $processor = new PutUnderMaintenanceProcessor(
      outputFactory: new EquipmentOutputFactory(),
      commandBus: $commandBus,
      authorization: $authorization,
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
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655453014');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new PutUnderMaintenanceProcessor(
      outputFactory: new EquipmentOutputFactory(),
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
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655453011');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new PutUnderMaintenanceCommand(organizationId: self::ORG_ID, equipmentId: self::EQUIP_ID)),
      [EquipmentNotFoundException::withId(self::EQUIP_ID)],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new PutUnderMaintenanceProcessor(
      outputFactory: new EquipmentOutputFactory(),
      commandBus: $commandBus,
      authorization: $authorization,
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
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655453012');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new PutUnderMaintenanceCommand(organizationId: self::ORG_ID, equipmentId: self::EQUIP_ID)),
      [EquipmentAlreadyDecommissionedException::withId(self::EQUIP_ID)],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new PutUnderMaintenanceProcessor(
      outputFactory: new EquipmentOutputFactory(),
      commandBus: $commandBus,
      authorization: $authorization,
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
  public function testProcessReturnsEquipmentOutputOnSuccess(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655453013');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $now = new DateTimeImmutable('2026-03-15T10:00:00+00:00');

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new PutUnderMaintenanceResult(
      equipmentId: self::EQUIP_ID,
      organizationId: self::ORG_ID,
      facilityId: '550e8400-e29b-41d4-a716-446655453099',
      type: 'fire_extinguisher',
      subType: null,
      brand: null,
      model: null,
      serialNumber: null,
      locationLabel: null,
      status: 'under_maintenance',
      installedAt: null,
      commissionedAt: null,
      tags: [],
      createdAt: $now,
      updatedAt: $now,
    ));

    $processor = new PutUnderMaintenanceProcessor(
      outputFactory: new EquipmentOutputFactory(),
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );

    self::assertInstanceOf(EquipmentOutput::class, $output);
    self::assertSame(self::EQUIP_ID, $output->id);
    self::assertSame('under_maintenance', $output->status);
  }

  #[Test]
  public function testProcessThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new PutUnderMaintenanceProcessor(
      outputFactory: new EquipmentOutputFactory(),
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $processor->process(data: null, operation: new Post(), uriVariables: $this->maintenanceUriVariables());
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

    $processor = new PutUnderMaintenanceProcessor(
      outputFactory: new EquipmentOutputFactory(),
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $this->maintenanceSecurity(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(data: null, operation: new Post(), uriVariables: $uriVariables);
  }

  #[Test]
  public function testProcessMapsADirectNotFoundToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->maintenanceProcessorThrowing(EquipmentNotFoundException::withId(self::EQUIP_ID))
      ->process(data: null, operation: new Post(), uriVariables: $this->maintenanceUriVariables());
  }

  #[Test]
  public function testProcessMapsADirectDecommissionedConflictToHttp409(): void
  {
    $this->expectException(ConflictHttpException::class);

    $this->maintenanceProcessorThrowing(EquipmentAlreadyDecommissionedException::withId(self::EQUIP_ID))
      ->process(data: null, operation: new Post(), uriVariables: $this->maintenanceUriVariables());
  }

  #[Test]
  public function testProcessMapsADirectInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Equipment is already under maintenance.');

    $this->maintenanceProcessorThrowing(new InvalidArgumentException('Equipment is already under maintenance.'))
      ->process(data: null, operation: new Post(), uriVariables: $this->maintenanceUriVariables());
  }

  #[Test]
  public function testProcessMapsAWrappedInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->maintenanceProcessorThrowing(
      $this->maintenanceWrapped(new InvalidArgumentException('Equipment is already under maintenance.')),
    )->process(data: null, operation: new Post(), uriVariables: $this->maintenanceUriVariables());
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $this->expectException(MessengerRuntimeException::class);

    $this->maintenanceProcessorThrowing($this->maintenanceWrapped(new RuntimeException('database is down')))
      ->process(data: null, operation: new Post(), uriVariables: $this->maintenanceUriVariables());
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

  /**
   * @return array<string, string>
   */
  private function maintenanceUriVariables(): array
  {
    return ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID];
  }

  private function maintenanceSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655453010'));

    return $security;
  }

  private function maintenanceWrapped(Throwable $failure): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new PutUnderMaintenanceCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
      )),
      [$failure],
    ));
  }

  private function maintenanceProcessorThrowing(Throwable $failure): PutUnderMaintenanceProcessor
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    return new PutUnderMaintenanceProcessor(
      outputFactory: new EquipmentOutputFactory(),
      commandBus: $commandBus,
      authorization: $authorization,
      security: $this->maintenanceSecurity(),
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
