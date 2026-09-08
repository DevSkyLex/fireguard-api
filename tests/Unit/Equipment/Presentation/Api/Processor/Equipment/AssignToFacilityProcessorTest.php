<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\UseCase\Command\Equipment\AssignToFacility\{AssignToFacilityCommand, AssignToFacilityResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Presentation\Api\Dto\Input\Equipment\AssignToFacilityInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Factory\EquipmentOutputFactory;
use Equipment\Presentation\Api\Processor\Equipment\AssignToFacilityProcessor;
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

#[CoversClass(AssignToFacilityProcessor::class)]
final class AssignToFacilityProcessorTest extends TestCase
{
  private const string ASSIGN_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441220';

  private const string ASSIGN_EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655441221';

  private const string ASSIGN_FACILITY_ID = '550e8400-e29b-41d4-a716-446655441222';

  #[Test]
  public function testProcessMapsWrappedEquipmentNotFoundToHttp404(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441200';
    $equipmentId = '550e8400-e29b-41d4-a716-446655441201';
    $facilityId = '550e8400-e29b-41d4-a716-446655441202';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441203');

    $input = new AssignToFacilityInput();
    $input->facilityId = $facilityId;

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), $organizationId, 'organization.equipment.write')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $handlerFailure = new HandlerFailedException(
      envelope: new Envelope(new AssignToFacilityCommand(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
        facilityId: $facilityId,
      )),
      exceptions: [EquipmentNotFoundException::withId($equipmentId)],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new AssignToFacilityProcessor(
      outputFactory: new EquipmentOutputFactory(),
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: [
        'organizationId' => $organizationId,
        'equipmentId' => $equipmentId,
      ],
    );
  }

  #[Test]
  public function testProcessReturnsEquipmentOutputOnSuccess(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441210';
    $equipmentId = '550e8400-e29b-41d4-a716-446655441211';
    $facilityId = '550e8400-e29b-41d4-a716-446655441212';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441213');

    $input = new AssignToFacilityInput();
    $input->facilityId = $facilityId;
    $input->installedAt = '2026-01-15T09:00:00+00:00';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $now = new DateTimeImmutable('2026-03-02T10:00:00+00:00');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(new AssignToFacilityResult(
        equipmentId: $equipmentId,
        organizationId: $organizationId,
        facilityId: $facilityId,
        type: 'fire_extinguisher',
        subType: null,
        brand: null,
        model: null,
        serialNumber: null,
        locationLabel: 'Floor 2, Room 201',
        status: 'in_stock',
        installedAt: '2026-01-15T09:00:00+00:00',
        commissionedAt: null,
        tags: [],
        createdAt: $now,
        updatedAt: $now,
      ));

    $processor = new AssignToFacilityProcessor(
      outputFactory: new EquipmentOutputFactory(),
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: [
        'organizationId' => $organizationId,
        'equipmentId' => $equipmentId,
      ],
    );

    self::assertInstanceOf(EquipmentOutput::class, $output);
    self::assertSame($facilityId, $output->facilityId);
    self::assertSame('Floor 2, Room 201', $output->locationLabel);
  }

  #[Test]
  public function testProcessThrowsAccessDeniedWhenPermissionMissing(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441220';
    $equipmentId = '550e8400-e29b-41d4-a716-446655441221';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441222');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), $organizationId, 'organization.equipment.write')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new AssignToFacilityProcessor(
      outputFactory: new EquipmentOutputFactory(),
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: new AssignToFacilityInput(),
      operation: new Post(),
      uriVariables: [
        'organizationId' => $organizationId,
        'equipmentId' => $equipmentId,
      ],
    );
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenOrganizationIsOutsideCallersScope(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441223';
    $equipmentId = '550e8400-e29b-41d4-a716-446655441224';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441225');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new AssignToFacilityProcessor(
      outputFactory: new EquipmentOutputFactory(),
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    // Not AccessDeniedHttpException: a 403 for a caller outside the organization's
    // scope would confirm the record exists across an organization boundary.
    try {
      $processor->process(
        data: new AssignToFacilityInput(),
        operation: new Post(),
        uriVariables: [
          'organizationId' => $organizationId,
          'equipmentId' => $equipmentId,
        ],
      );
      self::fail('Expected NotFoundHttpException to be thrown.');
    } catch (NotFoundHttpException $exception) {
      self::assertSame('Organization not found.', $exception->getMessage());
    }
  }

  #[Test]
  public function testProcessThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new AssignToFacilityProcessor(
      outputFactory: new EquipmentOutputFactory(),
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $processor->process(
      data: $this->assignInput(),
      operation: new Post(),
      uriVariables: $this->assignUriVariables(),
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

    $processor = new AssignToFacilityProcessor(
      outputFactory: new EquipmentOutputFactory(),
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $this->assignSecurity(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(data: $this->assignInput(), operation: new Post(), uriVariables: $uriVariables);
  }

  #[Test]
  public function testProcessMapsADirectNotFoundToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->assignProcessorThrowing(EquipmentNotFoundException::withId(self::ASSIGN_EQUIPMENT_ID))->process(
      data: $this->assignInput(),
      operation: new Post(),
      uriVariables: $this->assignUriVariables(),
    );
  }

  #[Test]
  public function testProcessMapsADirectInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Facility is in another organization.');

    $this->assignProcessorThrowing(new InvalidArgumentException('Facility is in another organization.'))->process(
      data: $this->assignInput(),
      operation: new Post(),
      uriVariables: $this->assignUriVariables(),
    );
  }

  #[Test]
  public function testProcessMapsAWrappedInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->assignProcessorThrowing(
      $this->assignWrapped(new InvalidArgumentException('Facility is in another organization.')),
    )->process(
      data: $this->assignInput(),
      operation: new Post(),
      uriVariables: $this->assignUriVariables(),
    );
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $this->expectException(MessengerRuntimeException::class);

    $this->assignProcessorThrowing($this->assignWrapped(new RuntimeException('database is down')))->process(
      data: $this->assignInput(),
      operation: new Post(),
      uriVariables: $this->assignUriVariables(),
    );
  }

  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function incompleteUriVariablesProvider(): iterable
  {
    yield 'no variables' => [[]];
    yield 'blank organizationId' => [['organizationId' => '', 'equipmentId' => self::ASSIGN_EQUIPMENT_ID]];
    yield 'missing equipmentId' => [['organizationId' => self::ASSIGN_ORGANIZATION_ID]];
    yield 'blank equipmentId' => [['organizationId' => self::ASSIGN_ORGANIZATION_ID, 'equipmentId' => '']];
  }

  /**
   * @return array<string, string>
   */
  private function assignUriVariables(): array
  {
    return [
      'organizationId' => self::ASSIGN_ORGANIZATION_ID,
      'equipmentId' => self::ASSIGN_EQUIPMENT_ID,
    ];
  }

  private function assignInput(): AssignToFacilityInput
  {
    $input = new AssignToFacilityInput();
    $input->facilityId = self::ASSIGN_FACILITY_ID;

    return $input;
  }

  private function assignSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441210'));

    return $security;
  }

  private function assignWrapped(Throwable $failure): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      envelope: new Envelope(new AssignToFacilityCommand(
        organizationId: self::ASSIGN_ORGANIZATION_ID,
        equipmentId: self::ASSIGN_EQUIPMENT_ID,
        facilityId: self::ASSIGN_FACILITY_ID,
      )),
      exceptions: [$failure],
    ));
  }

  private function assignProcessorThrowing(Throwable $failure): AssignToFacilityProcessor
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    return new AssignToFacilityProcessor(
      outputFactory: new EquipmentOutputFactory(),
      commandBus: $commandBus,
      authorization: $authorization,
      security: $this->assignSecurity(),
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
