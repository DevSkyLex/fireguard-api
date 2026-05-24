<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\NonConformity;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Command\NonConformity\AddNonConformity\{AddNonConformityCommand, AddNonConformityResult};
use Inspection\Domain\Exception\{InspectionAlreadyClosedException, InspectionNotFoundException};
use Inspection\Presentation\Api\Dto\Input\NonConformity\AddNonConformityInput;
use Inspection\Presentation\Api\Dto\Output\NonConformity\NonConformityOutput;
use Inspection\Presentation\Api\Processor\NonConformity\AddNonConformityProcessor;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

#[CoversClass(AddNonConformityProcessor::class)]
final class AddNonConformityProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string NC_ID = '550e8400-e29b-41d4-a716-446655440020';

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new AddNonConformityProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: new AddNonConformityInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessThrowsWhenUriVariablesMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $processor = new AddNonConformityProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: new AddNonConformityInput(),
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
      ->with(self::callback(static function (AddNonConformityCommand $command): bool {
        return self::ORG_ID === $command->organizationId
          && self::INSP_ID === $command->inspectionId
          && 'Fire extinguisher expired' === $command->description
          && 'high' === $command->severity;
      }))
      ->willReturn(new AddNonConformityResult(
        nonConformityId: self::NC_ID,
        inspectionId: self::INSP_ID,
        description: 'Fire extinguisher expired',
        severity: 'high',
        status: 'open',
        dueAt: '2026-02-15',
        notes: null,
        createdAt: $now,
        updatedAt: $now,
      ));

    $input = new AddNonConformityInput();
    $input->description = 'Fire extinguisher expired';
    $input->severity = 'high';
    $input->dueAt = '2026-02-15';

    $processor = new AddNonConformityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );

    self::assertInstanceOf(NonConformityOutput::class, $output);
    self::assertSame(self::NC_ID, $output->id);
    self::assertSame('open', $output->status);
    self::assertSame('high', $output->severity);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenInspectionMissing(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      commandException: InspectionNotFoundException::withId(self::INSP_ID),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessThrowsConflictWhenInspectionClosed(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      commandException: InspectionAlreadyClosedException::withId(self::INSP_ID),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: $this->makeInput(),
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
      data: $this->makeInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  private function makeAuthorizedProcessor(Throwable $commandException): AddNonConformityProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($commandException);

    return new AddNonConformityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );
  }

  private function makeInput(): AddNonConformityInput
  {
    $input = new AddNonConformityInput();
    $input->description = 'Test NC';
    $input->severity = 'low';

    return $input;
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
