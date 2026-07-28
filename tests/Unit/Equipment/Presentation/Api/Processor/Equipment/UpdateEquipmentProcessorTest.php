<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\UseCase\Command\Equipment\UpdateEquipment\{UpdateEquipmentCommand, UpdateEquipmentResult};
use Equipment\Domain\Exception\{EquipmentNotFoundException, EquipmentSerialNumberAlreadyExistsException};
use Equipment\Presentation\Api\Dto\Input\Equipment\UpdateEquipmentInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Processor\Equipment\UpdateEquipmentProcessor;
use InvalidArgumentException;
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

#[CoversClass(UpdateEquipmentProcessor::class)]
final class UpdateEquipmentProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655450001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655450002';

  #[Test]
  public function testProcessThrowsAccessDeniedWhenPermissionMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655450010');

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

    $processor = new UpdateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: new UpdateEquipmentInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProcessMapsWrappedNotFoundToHttp404(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655450011');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new UpdateEquipmentCommand(organizationId: self::ORG_ID, equipmentId: self::EQUIP_ID, type: 'fire_extinguisher')),
      [EquipmentNotFoundException::withId(self::EQUIP_ID)],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new UpdateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: new UpdateEquipmentInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProcessMapsWrappedSerialNumberConflictToHttp409(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655450012');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new UpdateEquipmentCommand(organizationId: self::ORG_ID, equipmentId: self::EQUIP_ID, type: 'fire_extinguisher')),
      [EquipmentSerialNumberAlreadyExistsException::withSerialNumber('EXT-001')],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new UpdateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: new UpdateEquipmentInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProcessReturnsEquipmentOutputOnSuccess(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655450013');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $now = new DateTimeImmutable('2026-03-15T10:00:00+00:00');

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new UpdateEquipmentResult(
      equipmentId: self::EQUIP_ID,
      organizationId: self::ORG_ID,
      facilityId: null,
      type: 'fire_extinguisher',
      subType: null,
      brand: 'Sicli',
      model: null,
      serialNumber: 'EXT-001',
      locationLabel: null,
      status: 'in_stock',
      installedAt: null,
      commissionedAt: null,
      tags: [],
      createdAt: $now,
      updatedAt: $now,
    ));

    $input = new UpdateEquipmentInput();
    $input->brand = 'Sicli';

    $processor = new UpdateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );

    self::assertInstanceOf(EquipmentOutput::class, $output);
    self::assertSame(self::EQUIP_ID, $output->id);
    self::assertSame('fire_extinguisher', $output->type);
    self::assertSame('Sicli', $output->brand);
  }

  #[Test]
  public function testProcessThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new UpdateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $processor->process(
      data: new UpdateEquipmentInput(),
      operation: new Patch(),
      uriVariables: $this->updateUriVariables(),
    );
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

    $processor = new UpdateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $this->updateSecurity(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(data: new UpdateEquipmentInput(), operation: new Patch(), uriVariables: $uriVariables);
  }

  #[Test]
  public function testProcessMapsADirectNotFoundToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->updateProcessorThrowing(EquipmentNotFoundException::withId(self::EQUIP_ID))->process(
      data: new UpdateEquipmentInput(),
      operation: new Patch(),
      uriVariables: $this->updateUriVariables(),
    );
  }

  #[Test]
  public function testProcessMapsADirectDuplicateSerialNumberToHttp409(): void
  {
    $this->expectException(ConflictHttpException::class);

    $this->updateProcessorThrowing(EquipmentSerialNumberAlreadyExistsException::withSerialNumber('SN-1'))->process(
      data: new UpdateEquipmentInput(),
      operation: new Patch(),
      uriVariables: $this->updateUriVariables(),
    );
  }

  #[Test]
  public function testProcessMapsADirectInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Unknown equipment type.');

    $this->updateProcessorThrowing(new InvalidArgumentException('Unknown equipment type.'))->process(
      data: new UpdateEquipmentInput(),
      operation: new Patch(),
      uriVariables: $this->updateUriVariables(),
    );
  }

  #[Test]
  public function testProcessMapsAWrappedInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->updateProcessorThrowing($this->updateWrapped(new InvalidArgumentException('Unknown equipment type.')))
      ->process(
        data: new UpdateEquipmentInput(),
        operation: new Patch(),
        uriVariables: $this->updateUriVariables(),
      );
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $this->expectException(MessengerRuntimeException::class);

    $this->updateProcessorThrowing($this->updateWrapped(new RuntimeException('database is down')))->process(
      data: new UpdateEquipmentInput(),
      operation: new Patch(),
      uriVariables: $this->updateUriVariables(),
    );
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
  private function updateUriVariables(): array
  {
    return ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID];
  }

  private function updateSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655450010'));

    return $security;
  }

  private function updateWrapped(Throwable $failure): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new UpdateEquipmentCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        type: 'extinguisher',
      )),
      [$failure],
    ));
  }

  private function updateProcessorThrowing(Throwable $failure): UpdateEquipmentProcessor
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    return new UpdateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $this->updateSecurity(),
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
