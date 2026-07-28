<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Command\Inspection\CloseInspection\CloseInspectionCommand;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Domain\Exception\{InspectionAlreadyClosedException, InspectionNotFoundException, InspectionNotSubmittedException};
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Factory\InspectionOutputFactory;
use Inspection\Presentation\Api\Processor\Inspection\CloseInspectionProcessor;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

#[CoversClass(CloseInspectionProcessor::class)]
final class CloseInspectionProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new CloseInspectionProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      outputMapper: $this->createOutputMapper(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessThrowsWhenUriVariablesMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $processor = new CloseInspectionProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      outputMapper: $this->createOutputMapper(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: null,
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

    $processor = new CloseInspectionProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
      outputMapper: $this->createOutputMapper(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessDispatchesCommandAndReturnsOutput(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (CloseInspectionCommand $command): bool {
        return self::ORG_ID === $command->organizationId
          && self::INSP_ID === $command->inspectionId;
      }));

    $now = new DateTimeImmutable('2026-01-15T12:00:00+00:00');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetInspectionResult(
        inspectionId: self::INSP_ID,
        organizationId: self::ORG_ID,
        equipmentId: '550e8400-e29b-41d4-a716-446655440002',
        facilityId: null,
        result: 'pass',
        status: 'closed',
        performedAt: '2026-01-15T10:00:00+00:00',
        inspectorType: 'user',
        inspectorName: 'John Doe',
        inspectorUserId: self::USER_ID,
        inspectorOrganizationName: null,
        checklistId: null,
        notes: null,
        signature: null,
        nonConformitiesCount: 0,
        createdAt: $now,
        updatedAt: $now,
      ));

    $processor = new CloseInspectionProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      outputMapper: $this->createOutputMapper(),
    );

    $output = $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );

    self::assertInstanceOf(InspectionOutput::class, $output);
    self::assertSame('closed', $output->status);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenInspectionMissing(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      commandException: InspectionNotFoundException::withId(self::INSP_ID),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessThrowsConflictWhenAlreadyClosed(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      commandException: InspectionAlreadyClosedException::withId(self::INSP_ID),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessThrowsConflictWhenNotYetSubmitted(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      commandException: InspectionNotSubmittedException::withId(self::INSP_ID),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsNotFoundFromMessengerException(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      commandException: MessengerRuntimeException::wrap(
        InspectionNotFoundException::withId(self::INSP_ID),
      ),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsConflictFromMessengerException(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      commandException: MessengerRuntimeException::wrap(
        InspectionAlreadyClosedException::withId(self::INSP_ID),
      ),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessThrowsBadRequestOnAnInvalidArgument(): void
  {
    $processor = $this->makeAuthorizedProcessor(new InvalidArgumentException('Malformed inspection identifier.'));

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsInvalidArgumentFromMessengerException(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      MessengerRuntimeException::wrap(new InvalidArgumentException('Malformed inspection identifier.')),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsNotSubmittedFromMessengerException(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      MessengerRuntimeException::wrap(InspectionNotSubmittedException::withId(self::INSP_ID)),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessRethrowsAnUnrelatedMessengerException(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      MessengerRuntimeException::wrap(new RuntimeException('Connection lost.')),
    );

    $this->expectException(MessengerRuntimeException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  private function makeAuthorizedProcessor(Throwable $commandException): CloseInspectionProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($commandException);

    return new CloseInspectionProcessor(
      commandBus: $commandBus,
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
      outputMapper: $this->createOutputMapper(),
    );
  }

  private function createOutputMapper(): InspectionOutputFactory
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('User lookup unavailable.'));

    return new InspectionOutputFactory($queryBus);
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
}
