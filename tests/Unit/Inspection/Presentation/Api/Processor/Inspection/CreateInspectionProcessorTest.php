<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Command\Inspection\CreateInspection\{CreateInspectionCommand, CreateInspectionResult};
use Inspection\Presentation\Api\Dto\Input\Inspection\CreateInspectionInput;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Factory\InspectionOutputFactory;
use Inspection\Presentation\Api\Processor\Inspection\CreateInspectionProcessor;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationQuotaExceededException;
use Organization\Domain\ValueObject\OrganizationQuotaResource;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(CreateInspectionProcessor::class)]
final class CreateInspectionProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new CreateInspectionProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      outputMapper: $this->createOutputMapper(),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: new CreateInspectionInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProcessThrowsWhenOrganizationIdMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $processor = new CreateInspectionProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      outputMapper: $this->createOutputMapper(),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: new CreateInspectionInput(),
      operation: new Post(),
      uriVariables: [],
    );
  }

  #[Test]
  public function testProcessThrowsWhenPermissionDenied(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $processor = new CreateInspectionProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: new CreateInspectionInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProcessDispatchesCommandAndReturnsOutput(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $now = new DateTimeImmutable('2026-01-15T11:00:00+00:00');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (CreateInspectionCommand $command): bool {
        return self::ORG_ID === $command->organizationId
          && self::EQUIP_ID === $command->equipmentId
          && 'pass' === $command->result
          && 'user' === $command->inspectorType;
      }))
      ->willReturn(new CreateInspectionResult(
        inspectionId: self::INSP_ID,
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        facilityId: null,
        result: 'pass',
        status: 'draft',
        performedAt: '2026-01-15T10:00:00+00:00',
        inspectorType: 'user',
        inspectorName: 'John Doe',
        inspectorUserId: self::USER_ID,
        inspectorOrganizationName: null,
        checklistId: null,
        notes: null,
        signature: null,
        createdAt: $now,
        updatedAt: $now,
      ));

    $input = new CreateInspectionInput();
    $input->equipmentId = self::EQUIP_ID;
    $input->result = 'pass';
    $input->performedAt = '2026-01-15T10:00:00+00:00';
    $input->inspectorType = 'user';
    $input->inspectorName = 'John Doe';
    $input->inspectorUserId = self::USER_ID;

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );

    self::assertInstanceOf(InspectionOutput::class, $output);
    self::assertSame(self::INSP_ID, $output->id);
    self::assertSame(self::ORG_ID, $output->organizationId);
    self::assertSame('draft', $output->status);
    self::assertSame('pass', $output->result);
    self::assertSame(0, $output->nonConformitiesCount);
  }

  #[Test]
  public function testProcessThrowsBadRequestOnInvalidArgument(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(new InvalidArgumentException('Invalid input.'));

    $input = new CreateInspectionInput();
    $input->equipmentId = self::EQUIP_ID;
    $input->result = 'pass';
    $input->performedAt = '2026-01-15T10:00:00+00:00';
    $input->inspectorType = 'user';
    $input->inspectorName = 'John Doe';

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid input.');

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsInvalidArgumentFromMessengerException(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap(
        new InvalidArgumentException('Wrapped invalid argument.'),
      ));

    $input = new CreateInspectionInput();
    $input->equipmentId = self::EQUIP_ID;
    $input->result = 'pass';
    $input->performedAt = '2026-01-15T10:00:00+00:00';
    $input->inspectorType = 'user';
    $input->inspectorName = 'John Doe';

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Wrapped invalid argument.');

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProcessDefaultsInspectorUserIdToAuthenticatedUserForUserType(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $now = new DateTimeImmutable('2026-01-15T11:00:00+00:00');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (CreateInspectionCommand $command): bool {
        return 'user' === $command->inspectorType
          && self::USER_ID === $command->inspectorUserId;
      }))
      ->willReturn(new CreateInspectionResult(
        inspectionId: self::INSP_ID,
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        facilityId: null,
        result: 'pass',
        status: 'draft',
        performedAt: '2026-01-15T10:00:00+00:00',
        inspectorType: 'user',
        inspectorName: 'John Doe',
        inspectorUserId: self::USER_ID,
        inspectorOrganizationName: null,
        checklistId: null,
        notes: null,
        signature: null,
        createdAt: $now,
        updatedAt: $now,
      ));

    $input = new CreateInspectionInput();
    $input->equipmentId = self::EQUIP_ID;
    $input->result = 'pass';
    $input->performedAt = '2026-01-15T10:00:00+00:00';
    $input->inspectorType = 'user';
    $input->inspectorName = 'John Doe';
    // inspectorUserId intentionally left null: the processor must attribute the
    // inspection to the authenticated user for the USER inspector type.

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );

    self::assertInstanceOf(InspectionOutput::class, $output);
    self::assertSame(self::INSP_ID, $output->id);
  }

  #[Test]
  public function testProcessMapsWrappedQuotaExceededToHttp409(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new CreateInspectionCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        result: 'pass',
        performedAt: '2026-01-15T10:00:00+00:00',
        inspectorType: 'user',
        inspectorName: 'John Doe',
        inspectorUserId: self::USER_ID,
      )),
      [OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::INSPECTIONS, 100)],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $input = new CreateInspectionInput();
    $input->equipmentId = self::EQUIP_ID;
    $input->result = 'pass';
    $input->performedAt = '2026-01-15T10:00:00+00:00';
    $input->inspectorType = 'user';
    $input->inspectorName = 'John Doe';
    $input->inspectorUserId = self::USER_ID;

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }

  private function createOutputMapper(): InspectionOutputFactory
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('User lookup unavailable.'));

    return new InspectionOutputFactory($queryBus);
  }
}
